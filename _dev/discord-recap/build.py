"""Read a Discord data package and write private/discord/data.js (window.RECAP) for private/discord/index.html.
Update the live page with:  bash _dev/discord-recap/update.sh <unzipped package folder>

Usage:  py -I build.py D:/package            (full: messages + activity logs, a few minutes)
        py -I build.py D:/package --quick    (messages + account only, seconds)
"""
import collections, datetime as dt, json, os, re, sys

PKG = sys.argv[1]
QUICK = '--quick' in sys.argv
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'private', 'discord', 'data.js')  # gitignored: personal, server-only
TOP = 10

# ---------- time: UTC -> UK local (no tzdata on this machine) ----------
def _last_sunday(y, m):
    d = dt.date(y, m, 31)
    return d - dt.timedelta(days=(d.weekday() + 1) % 7)

_BST = {}
def uk(t):  # t: naive UTC datetime
    y = t.year
    if y not in _BST:
        _BST[y] = (dt.datetime.combine(_last_sunday(y, 3), dt.time(1)), dt.datetime.combine(_last_sunday(y, 10), dt.time(1)))
    s, e = _BST[y]
    return t + dt.timedelta(hours=1) if s <= t < e else t

def parse_iso(s):  # '2021-12-21T21:11:46.354Z' or '2021-12-21T21:11:46Z'
    return dt.datetime.strptime(s.strip('"')[:19], '%Y-%m-%dT%H:%M:%S')

def keys(t):
    return ('all', str(t.year), f'{t.year}-{t.month:02}')

# ---------- accumulators ----------
NUMS = ('msgs', 'att', 'react', 'voice_ms', 'voice_events', 'streamed_s', 'watched_s', 'stream_events', 'opens', 'speak', 'listen')
LISTS = ('people', 'groups', 'vc', 'react_emoji', 'words', 'emoji', 'places', 'games', 'files')

def new_period():
    p = {k: 0 for k in NUMS}
    p.update({k: collections.Counter() for k in LISTS})
    p['heat'] = [[0] * 24 for _ in range(7)]
    p['days'] = set()
    p['first'] = None
    return p

P = collections.defaultdict(new_period)

# ---------- names ----------
def load(path, default=None):
    try:
        with open(path, encoding='utf-8') as f:
            return json.load(f)
    except FileNotFoundError:
        return default

M = os.path.join(PKG, 'Messages')
chan_index = load(os.path.join(M, 'index.json'), {})
server_index = load(os.path.join(PKG, 'Servers', 'index.json'), {})

def clean_channel_name(cid):
    n = chan_index.get(cid) or ''
    if n.startswith('Direct Message with '):
        return 'DM · ' + n[len('Direct Message with '):]
    if n.startswith('Unknown channel'):
        return None
    return n or None

# ---------- messages ----------
STOP = set('the and you that this for but not are was with have just its it\'s can like what all get out about yeah your they don\'t dont its i\'m im too how one when there then got would will from know think lol just yes now see good also why some more because make really any did does been had has him her she his our out who were which them than could time want need well even here into only over very much going thing still way use did let off over sure back said say these those then where while should after before going gonna thats ill cant didnt doesnt isnt wasnt youre ive idk'.split())
URL = re.compile(r'https?://\S+')
CUSTOM = re.compile(r'<a?:(\w+):\d+>')
UNI = re.compile('[\U0001F1E6-\U0001F1FF\U0001F300-\U0001FAFF\u2600-\u27BF]')
WORD = re.compile(r"\b[a-z][a-z']{2,}\b")  # whole words only, so "5tgv" mashes don't yield "tgv"
IMG, VID = {'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'heic'}, {'mp4', 'mov', 'webm', 'mkv', 'avi'}

def file_kind(url):
    ext = url.split('?')[0].rsplit('.', 1)[-1].lower()
    return 'Images' if ext in IMG else 'Videos' if ext in VID else 'Other'

def scan_messages():
    for d in os.listdir(M):
        cdir = os.path.join(M, d)
        if not os.path.isdir(cdir):
            continue
        ch = load(os.path.join(cdir, 'channel.json'), {})
        cid = ch.get('id', d[1:])
        if ch.get('guild'):
            where = f"{ch['guild'].get('name', '?')} #{ch.get('name', '?')}"
        else:
            where = clean_channel_name(cid) or ('Group DM' if ch.get('type') == 'GROUP_DM' else 'Unknown channel')
        for m in load(os.path.join(cdir, 'messages.json'), []):
            t = uk(dt.datetime.strptime(m['Timestamp'][:19], '%Y-%m-%d %H:%M:%S'))
            text = m.get('Contents') or ''
            files = (m.get('Attachments') or '').split()
            body = URL.sub(' ', text)
            words = {w for w in WORD.findall(body.lower()) if w not in STOP}  # once per message, so spam can't dominate
            emo = [f':{n}:' for n in CUSTOM.findall(body)] + UNI.findall(CUSTOM.sub(' ', body))
            for k in keys(t):
                p = P[k]
                p['msgs'] += 1
                p['att'] += len(files)
                for f in files:
                    p['files'][file_kind(f)] += 1
                p['places'][where] += 1
                p['words'].update(words)
                p['emoji'].update(emo)
                p['heat'][t.weekday()][t.hour] += 1
                p['days'].add(t.date())
                if p['first'] is None or t < p['first'][0]:
                    p['first'] = (t, text[:160], where)

# ---------- activity logs (one line = one JSON event) ----------
def events(path, wanted):
    prefixes = tuple(b'{"event_type":"%s"' % w.encode() for w in wanted)
    with open(path, 'rb') as f:
        for line in f:
            if line.startswith(prefixes):
                yield json.loads(line)

def num(v):
    try:
        return float(v)
    except (TypeError, ValueError):
        return 0.0

def scan_modeling():
    path = os.path.join(PKG, 'Activity', 'modeling', 'events-2026-00000-of-00001.json')
    wanted = ('add_reaction', 'leave_voice_channel', 'video_stream_ended', 'app_opened', 'start_speaking', 'start_listening')
    for e in events(path, wanted):
        et, t = e['event_type'], uk(parse_iso(e['timestamp']))
        for k in keys(t):
            p = P[k]
            if et == 'add_reaction':
                p['react'] += 1
                if e.get('emoji_name'):
                    n = e['emoji_name']  # custom emoji have word names; unicode ones are the emoji itself
                    p['react_emoji'][f':{n}:' if re.fullmatch(r'\w+', n, re.ASCII) else n] += 1
            elif et == 'leave_voice_channel':
                ms = num(e.get('duration'))
                p['voice_events'] += 1
                p['voice_ms'] += ms
                ct, cid = str(e.get('channel_type')), str(e.get('channel_id'))
                if ct == '1':
                    p['people'][(clean_channel_name(cid) or 'DM · unknown').replace('DM · ', '')] += ms
                elif ct == '3':
                    p['groups'][clean_channel_name(cid) or f'Group DM …{cid[-4:]}'] += ms
                else:
                    srv = server_index.get(str(e.get('guild_id')), 'Server I left')
                    cn = clean_channel_name(cid)
                    if cn and cn.endswith(', ' + srv):  # index.json names guild channels "channel, server"
                        cn = cn[:-len(srv) - 2]
                    p['vc'][f'{srv} › {cn}' if cn else srv] += ms
            elif et == 'video_stream_ended':
                p['stream_events'] += 1
                role = e.get('participant_type')
                secs = num(e.get('duration')) or sum(num(v) for k2, v in e.items() if k2.startswith('duration_connection_type_'))
                if role == 'streamer':
                    p['streamed_s'] += secs
                elif role == 'receiver':
                    p['watched_s'] += secs
            elif et == 'app_opened':
                p['opens'] += 1
            elif et == 'start_speaking':
                p['speak'] += 1
            elif et == 'start_listening':
                p['listen'] += 1

def scan_games():
    # activity_updated type 0 = playing; a session's length = first to last update
    path = os.path.join(PKG, 'Activity', 'analytics', 'events-2026-00000-of-00001.json')
    sess = {}
    for e in events(path, ('activity_updated',)):
        if str(e.get('type')) != '0' or not e.get('name'):
            continue
        t = uk(parse_iso(e['timestamp']))
        sid = e.get('activity_session_id') or (e['name'], t.date())
        s = sess.get(sid)
        if s is None:
            sess[sid] = [e['name'], t, t]
        else:
            s[1], s[2] = min(s[1], t), max(s[2], t)
    for name, a, b in sess.values():
        for k in keys(a):
            P[k]['games'][name] += (b - a).total_seconds()

# ---------- finish ----------
def streak(days):
    best, run, prev, best_end = 0, 0, None, None
    for d in sorted(days):
        run = run + 1 if prev and (d - prev).days == 1 else 1
        if run > best:
            best, best_end = run, d
        prev = d
    return {'days': best, 'start': str(best_end - dt.timedelta(days=best - 1)), 'end': str(best_end)} if best else None

def finish():
    out = {}
    for k, p in P.items():
        y = int(k[:4]) if k != 'all' else None
        is_month = len(k) == 7
        voice_tracked = p['voice_events'] > 0 or (y is not None and y >= 2020)
        stream_tracked = k == 'all' or (y is not None and (y > 2020 or (y == 2020 and (not is_month or int(k[5:]) >= 3))))
        h = lambda c: [[n, round(v / 3600000, 1)] for n, v in c.most_common(TOP) if v > 0]
        out[k] = {
            'msgs': p['msgs'], 'att': p['att'], 'react': p['react'] if not QUICK else None,
            'voice_h': round(p['voice_ms'] / 3600000, 1) if voice_tracked and not QUICK else None,
            'voice_sessions': p['voice_events'],
            'streamed_h': round(p['streamed_s'] / 3600, 1) if stream_tracked and not QUICK else None,
            'watched_h': round(p['watched_s'] / 3600, 1) if stream_tracked and not QUICK else None,
            'opens': p['opens'] if not QUICK else None, 'speak': p['speak'], 'listen': p['listen'],
            'people': h(p['people']), 'groups': h(p['groups']), 'vc': h(p['vc']),
            'react_emoji': p['react_emoji'].most_common(TOP), 'words': p['words'].most_common(TOP),
            'emoji': p['emoji'].most_common(TOP), 'places': p['places'].most_common(TOP),
            'games': [[n, round(v / 3600, 1)] for n, v in p['games'].most_common(TOP) if v >= 360],
            'files': dict(p['files']), 'heat': p['heat'], 'streak': streak(p['days']),
            'first': {'ts': p['first'][0].strftime('%Y-%m-%d %H:%M'), 'text': p['first'][1], 'where': p['first'][2]} if p['first'] else None,
        }
    return out

def main():
    scan_messages()
    print('messages done', flush=True)
    if not QUICK:
        scan_modeling()
        print('modeling log done', flush=True)
        scan_games()
        print('games done', flush=True)
    data = finish()
    years = sorted(int(k) for k in data if len(k) == 4)
    # self-check: yearly totals add up to all-time, months add up to years
    assert sum(data[str(y)]['msgs'] for y in years) == data['all']['msgs']
    for y in years:
        assert sum(data[k]['msgs'] for k in data if k.startswith(f'{y}-')) == data[str(y)]['msgs']
    user = load(os.path.join(PKG, 'Account', 'user.json'), {})
    recap = {'generated': dt.datetime.now().strftime('%Y-%m-%d %H:%M'), 'quick': QUICK, 'username': user.get('global_name') or user.get('username'),
             'years': years, 'last_day': max(d for p in P.values() for d in p['days']).isoformat(), 'periods': data}
    with open(OUT, 'w', encoding='utf-8') as f:
        f.write('window.RECAP=' + json.dumps(recap, ensure_ascii=False, separators=(',', ':')) + ';\n')
    print(f'wrote {OUT} ({os.path.getsize(OUT) // 1024} KB)')
    for y in years:
        d = data[str(y)]
        print(y, 'msgs', d['msgs'], 'att', d['att'], 'react', d['react'], 'voice_h', d['voice_h'], 'streamed_h', d['streamed_h'], 'watched_h', d['watched_h'])

if __name__ == '__main__':
    main()
