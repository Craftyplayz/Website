"""Render a .litematic build to a transparent PNG, using the block models and textures from a Minecraft client jar.

Usage: py -I render.py <file.litematic> <client.jar> <out.png> [--side south|north|east|west] [--yaw 20] [--pitch 18]
       [--keep substr,substr] [--px 16] [--height 2000]
Orthographic, Minecraft face shading, z-buffered. --keep: only blocks whose id contains one of these (default: everything).
"""
import argparse, io, json, math, os, sys, zipfile
import numpy as np
from PIL import Image
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))  # py -I drops the script's own folder
from litematic import load

DIRS = {'north': (0, 0, -1), 'south': (0, 0, 1), 'east': (1, 0, 0), 'west': (-1, 0, 0), 'up': (0, 1, 0), 'down': (0, -1, 0)}
SHADE = {'up': 1.0, 'down': 0.5, 'north': 0.8, 'south': 0.8, 'east': 0.6, 'west': 0.6}
SIDE_YAW = {'south': 0, 'west': 90, 'north': 180, 'east': 270}  # camera on that side, looking at it


class Assets:
    def __init__(self, jar):
        self.z = zipfile.ZipFile(jar); self.models = {}; self.tex = {}
    def json(self, path):
        return json.loads(self.z.read(path))
    def texture(self, ref):
        ref = ref.split(':')[-1]
        if ref not in self.tex:
            im = Image.open(io.BytesIO(self.z.read(f'assets/minecraft/textures/{ref}.png'))).convert('RGBA')
            self.tex[ref] = np.asarray(im.crop((0, 0, im.width, im.width)), dtype=np.float32)  # animated: first frame
        return self.tex[ref]
    def model(self, name):
        name = name.split(':')[-1]
        if name in self.models: return self.models[name]
        m = self.json(f'assets/minecraft/models/{name}.json')
        tex, elements = {}, None
        if 'parent' in m and not m['parent'].startswith('builtin'):
            p = self.model(m['parent']); tex.update(p[0]); elements = p[1]
        tex.update(m.get('textures', {}))
        elements = m.get('elements', elements)
        self.models[name] = (tex, elements)
        return self.models[name]


def resolve(tex, ref, depth=0):
    while ref.startswith('#') and depth < 10:
        ref = tex.get(ref[1:], ''); depth += 1
    return ref or None


def matches(when, props):
    if 'OR' in when: return any(matches(w, props) for w in when['OR'])
    if 'AND' in when: return all(matches(w, props) for w in when['AND'])
    return all(str(props.get(k)) in str(v).split('|') for k, v in when.items())


def rot_point(p, rx, ry):
    x, y, z = p
    for _ in range((rx // 90) % 4):  # around X: up -> north, like Minecraft's x rotation
        y, z = 16 - z, y
    for _ in range((ry // 90) % 4):  # around Y: north -> east (clockwise from above)
        x, z = 16 - z, x
    return (x, y, z)


def face_corners(f, t, face):
    (x0, y0, z0), (x1, y1, z1) = f, t
    return {  # P0 (u=0,v=0), P1 (u=1), P3 (v=1)
        'south': ((x0, y1, z1), (x1, y1, z1), (x0, y0, z1)),
        'north': ((x1, y1, z0), (x0, y1, z0), (x1, y0, z0)),
        'east': ((x1, y1, z1), (x1, y1, z0), (x1, y0, z1)),
        'west': ((x0, y1, z0), (x0, y1, z1), (x0, y0, z0)),
        'up': ((x0, y1, z0), (x1, y1, z0), (x0, y1, z1)),
        'down': ((x0, y0, z1), (x1, y0, z1), (x0, y0, z0)),
    }[face]


def default_uv(f, t, face):
    (x0, y0, z0), (x1, y1, z1) = f, t
    return {'south': (x0, 16 - y1, x1, 16 - y0), 'north': (16 - x1, 16 - y1, 16 - x0, 16 - y0),
            'east': (16 - z1, 16 - y1, 16 - z0, 16 - y0), 'west': (z0, 16 - y1, z1, 16 - y0),
            'up': (x0, z0, x1, z1), 'down': (x0, 16 - z1, x1, 16 - z0)}[face]


def dir_after(d, rx, ry):
    v = rot_point(tuple(8 + 8 * c for c in DIRS[d]), rx, ry)
    v = tuple(round((c - 8) / 8) for c in v)
    return next(k for k, dv in DIRS.items() if dv == v)


def state_quads(assets, state):
    """List of (P0, P1, P3 in block units 0..1, tex array, uv, normal dir, cullface dir or None)."""
    name, _, rest = state.partition('[')
    props = dict(kv.split('=') for kv in rest.rstrip(']').split(',')) if rest else {}
    bs = assets.json(f'assets/minecraft/blockstates/{name.split(":")[-1]}.json')
    picks = []
    if 'variants' in bs:
        for key, v in bs['variants'].items():
            cond = dict(kv.split('=') for kv in key.split(',')) if key else {}
            if all(props.get(k) == val for k, val in cond.items()):
                picks.append(v[0] if isinstance(v, list) else v); break
    for part in bs.get('multipart', []):
        if 'when' not in part or matches(part['when'], props):
            a = part['apply']; picks.append(a[0] if isinstance(a, list) else a)
    quads, full = [], False
    for pick in picks:
        tex, elements = assets.model(pick['model'])
        rx, ry = pick.get('x', 0), pick.get('y', 0)
        for el in elements or []:
            f, t = el['from'], el['to']
            if f == [0, 0, 0] and t == [16, 16, 16] and len(elements) == 1: full = True
            for face, fd in el.get('faces', {}).items():
                ref = resolve(tex, fd['texture'])
                if not ref: continue
                c = [rot_point(p, rx, ry) for p in face_corners(f, t, face)]
                quads.append(([tuple(v / 16 for v in p) for p in c], assets.texture(ref),
                              fd.get('uv') or default_uv(f, t, face), dir_after(face, rx, ry),
                              dir_after(fd['cullface'], rx, ry) if 'cullface' in fd else None))
    return quads, full and 'glass' not in name


def render(path, jar, side='south', yaw=20.0, pitch=18.0, keep=None, px=16):
    names, grid = load(path)
    if keep:
        drop = [i for i, n in enumerate(names) if i and not any(k in n for k in keep)]
        grid[np.isin(grid, drop)] = 0
    assets = Assets(jar)
    info = {}
    for i in np.unique(grid):
        if i: info[i] = state_quads(assets, names[i])
    full = np.zeros(len(names), bool)
    for i, (_, f) in info.items(): full[i] = f
    isfull = full[grid]

    ya, pa = math.radians(SIDE_YAW[side] + yaw), math.radians(pitch)
    d = np.array([math.sin(ya) * math.cos(pa), math.sin(pa), math.cos(ya) * math.cos(pa)])  # towards the camera
    f = -d; right = np.cross(f, [0, 1, 0]); right /= np.linalg.norm(right); up = np.cross(right, f)

    H, D, W = grid.shape
    corners = np.array([[x, y, z] for x in (0, W) for y in (0, H) for z in (0, D)], float)
    sx, sy = corners @ right * px, -(corners @ up) * px
    ox, oy = -sx.min() + 2, -sy.min() + 2
    w, h = int(sx.max() - sx.min()) + 4, int(sy.max() - sy.min()) + 4
    color = np.zeros((h, w, 4), np.float32); zbuf = np.full((h, w), -np.inf, np.float32)

    ys, zs, xs = np.nonzero(grid)
    for y, z, x in zip(ys, zs, xs):
        for P, tex, uv, ndir, cull in info[grid[y, z, x]][0]:
            n = np.array(DIRS[ndir])
            if n @ d <= 1e-6: continue  # facing away
            if cull:
                cx, cy, cz = np.array([x, y, z]) + DIRS[cull]
                if 0 <= cy < H and 0 <= cz < D and 0 <= cx < W and isfull[cy, cz, cx]: continue
            P0, P1, P3 = (np.array(p) + (x, y, z) for p in P)
            s = lambda p: np.array([p @ right * px + ox, -(p @ up) * px + oy])
            a, b, c = s(P0), s(P1), s(P3)
            e1, e2 = b - a, c - a
            det = e1[0] * e2[1] - e1[1] * e2[0]
            if abs(det) < 1e-9: continue
            pts = np.array([a, b, c, b + e2])
            x0, y0 = np.floor(pts.min(0)).astype(int); x1, y1 = np.ceil(pts.max(0)).astype(int)
            x0, y0, x1, y1 = max(x0, 0), max(y0, 0), min(x1, w), min(y1, h)
            if x1 <= x0 or y1 <= y0: continue
            gx, gy = np.meshgrid(np.arange(x0, x1) + .5 - a[0], np.arange(y0, y1) + .5 - a[1])
            u = (gx * e2[1] - gy * e2[0]) / det; v = (gy * e1[0] - gx * e1[1]) / det
            inside = (u >= 0) & (u < 1) & (v >= 0) & (v < 1)
            if not inside.any(): continue
            dep = (P0 @ d) + u * ((P1 - P0) @ d) + v * ((P3 - P0) @ d)
            zb = zbuf[y0:y1, x0:x1]
            ok = inside & (dep > zb + 1e-4)
            if not ok.any(): continue
            ts = tex.shape[0] / 16
            tu = np.clip(((uv[0] + u * (uv[2] - uv[0])) * ts).astype(int), 0, tex.shape[1] - 1)
            tv = np.clip(((uv[1] + v * (uv[3] - uv[1])) * ts).astype(int), 0, tex.shape[0] - 1)
            texel = tex[tv, tu]
            ok &= texel[..., 3] > 127
            if not ok.any(): continue
            col = color[y0:y1, x0:x1]
            col[ok, :3] = texel[ok, :3] * SHADE[ndir]; col[ok, 3] = 255
            zb[ok] = dep[ok]
    img = Image.fromarray(color.clip(0, 255).astype(np.uint8), 'RGBA')
    return img.crop(img.getbbox())


if __name__ == '__main__':
    ap = argparse.ArgumentParser()
    ap.add_argument('schematic'); ap.add_argument('jar'); ap.add_argument('out')
    ap.add_argument('--side', default='south', choices=SIDE_YAW); ap.add_argument('--yaw', type=float, default=20)
    ap.add_argument('--pitch', type=float, default=18); ap.add_argument('--keep', default='')
    ap.add_argument('--px', type=int, default=16); ap.add_argument('--height', type=int, default=0)
    a = ap.parse_args()
    img = render(a.schematic, a.jar, a.side, a.yaw, a.pitch, [k for k in a.keep.split(',') if k], a.px)
    if a.height and img.height > a.height:
        img = img.resize((round(img.width * a.height / img.height), a.height), Image.LANCZOS)
    img.save(a.out); print(a.out, img.size)
