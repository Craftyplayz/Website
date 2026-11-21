"""Read a Litematica .litematic file into a numpy block grid.

load(path) -> (names, grid): names[i] = 'minecraft:stone_stairs[facing=north,half=bottom]' style strings
(name + sorted properties), grid[y, z, x] = palette index (0 is air). Regions are merged at their positions.
"""
import gzip, struct
import numpy as np


def _nbt(buf, pos, tag):
    def rd(fmt, n):
        nonlocal pos
        v = struct.unpack_from('>' + fmt, buf, pos)[0]; pos += n; return v
    def string():
        nonlocal pos
        n = rd('H', 2); s = buf[pos:pos + n].decode('utf-8', 'replace'); pos += n; return s
    def value(t):
        nonlocal pos
        if t == 1: return rd('b', 1)
        if t == 2: return rd('h', 2)
        if t == 3: return rd('i', 4)
        if t == 4: return rd('q', 8)
        if t == 5: return rd('f', 4)
        if t == 6: return rd('d', 8)
        if t == 7: n = rd('i', 4); v = buf[pos:pos + n]; pos += n; return v
        if t == 8: return string()
        if t == 9:
            et = rd('b', 1); n = rd('i', 4); return [value(et) for _ in range(n)]
        if t == 10:
            d = {}
            while True:
                ct = rd('b', 1)
                if ct == 0: return d
                k = string(); d[k] = value(ct)
        if t == 11: n = rd('i', 4); v = np.frombuffer(buf, '>i4', n, pos); pos += 4 * n; return v
        if t == 12: n = rd('i', 4); v = np.frombuffer(buf, '>i8', n, pos); pos += 8 * n; return v
        raise ValueError(f'bad NBT tag {t}')
    return value(tag), pos


def read_nbt(path):
    buf = gzip.open(path).read()
    assert buf[0] == 10, 'root must be a compound'
    n = struct.unpack_from('>H', buf, 1)[0]
    root, _ = _nbt(buf, 3 + n, 10)
    return root


def unpack(longs, bits, count):
    """Litematica packs values back to back, spanning long boundaries."""
    L = np.asarray(longs, dtype='>i8').astype(np.uint64)
    L = np.append(L, np.uint64(0))  # so a+1 is always valid
    idx = np.arange(count, dtype=np.uint64)
    start = idx * np.uint64(bits)
    a = (start >> np.uint64(6)).astype(np.int64)
    o = start & np.uint64(63)
    lo = L[a] >> o
    # bits that spill into the next long; shift by 64 is undefined, so mask those out
    spill = (o + np.uint64(bits)) > np.uint64(64)
    sh = np.where(spill, np.uint64(64) - o, np.uint64(0))
    hi = np.where(spill, L[a + 1] << sh, np.uint64(0))
    return ((lo | hi) & np.uint64((1 << bits) - 1)).astype(np.int64)


def state_name(entry):
    props = entry.get('Properties') or {}
    return entry['Name'] + ('[' + ','.join(f'{k}={props[k]}' for k in sorted(props)) + ']' if props else '')


def load(path):
    root = read_nbt(path)
    regions = []
    for reg in root['Regions'].values():
        pos = np.array([reg['Position'][k] for k in 'xyz']); size = np.array([reg['Size'][k] for k in 'xyz'])
        lo = pos + np.where(size < 0, size + 1, 0)
        sx, sy, sz = np.abs(size)
        pal = [state_name(e) for e in reg['BlockStatePalette']]
        bits = max(2, (len(pal) - 1).bit_length())
        vals = unpack(reg['BlockStates'], bits, sx * sy * sz).reshape(sy, sz, sx)
        regions.append((lo, pal, vals))
    names = ['minecraft:air']; index = {'minecraft:air': 0}
    lo = np.min([r[0] for r in regions], axis=0)
    hi = np.max([r[0] + np.array(r[2].shape)[[2, 0, 1]] for r in regions], axis=0)
    grid = np.zeros((hi[1] - lo[1], hi[2] - lo[2], hi[0] - lo[0]), dtype=np.int32)
    for rlo, pal, vals in regions:
        remap = np.array([index.setdefault(n, len(index)) for n in pal])
        for n in pal:
            if n not in names: names.append(n)
        names = sorted(index, key=index.get)
        o = rlo - lo
        sub = remap[vals]
        tgt = grid[o[1]:o[1] + sub.shape[0], o[2]:o[2] + sub.shape[1], o[0]:o[0] + sub.shape[2]]
        np.copyto(tgt, sub, where=sub != 0)
    return names, grid


if __name__ == '__main__':
    # self-check: unpack() against a slow reference, incl. values that span two longs
    rng = np.random.default_rng(1)
    for bits in (2, 5, 7, 13):
        vals = rng.integers(0, 1 << bits, 500)
        acc, out = 0, []
        for i, v in enumerate(vals): acc |= int(v) << (i * bits)
        n = -(-500 * bits // 64)
        longs = [(acc >> (64 * k)) & ((1 << 64) - 1) for k in range(n)]
        longs = np.array([l - (1 << 64) if l >= 1 << 63 else l for l in longs], dtype=np.int64)
        assert (unpack(longs, bits, 500) == vals).all(), bits
    print('unpack self-check ok')
