"""Print one /give command: a chest holding 4 shulker boxes with a full playing-card deck and a full Uno deck.

Cards are gold nuggets whose custom name is the card name (see _dev/handoffs/card-pack.md).
Usage: py -I deck_command.py > deck-chest-command.txt   (paste into a command block: chat allows only 256 characters)
"""
SUITS = ['Spades', 'Hearts', 'Diamonds', 'Clubs']
RANKS = ['Ace'] + [str(n) for n in range(2, 11)] + ['Jack', 'Queen', 'King']
COLOURS = ['Red', 'Yellow', 'Green', 'Blue']


def playing():
    return [(f'{r} of {s}', 1) for s in SUITS for r in RANKS] + [('Joker', 2)]


def uno():
    cards = []
    for c in COLOURS:
        cards.append((f'{c} 0', 1))
        cards += [(f'{c} {n}', 2) for n in range(1, 10)]
        cards += [(f'{c} {a}', 2) for a in ('Skip', 'Reverse', '+2')]
    return cards + [('Wild', 4), ('Wild +4', 4)]


def nugget(slot, name, count):
    return f'{{slot:{slot},item:{{id:"minecraft:gold_nugget",count:{count},components:{{"minecraft:custom_name":"{name}"}}}}}}'


def shulker(slot, colour, label, cards):
    assert len(cards) <= 27, label
    inner = ','.join(nugget(i, n, c) for i, (n, c) in enumerate(cards))
    return (f'{{slot:{slot},item:{{id:"minecraft:{colour}_shulker_box",count:1,components:'
            f'{{"minecraft:custom_name":"{label}","minecraft:container":[{inner}]}}}}}}')


def command():
    p, u = playing(), uno()
    boxes = [shulker(0, 'red', 'Playing cards 1/2', p[:27]), shulker(1, 'red', 'Playing cards 2/2', p[27:]),
             shulker(2, 'blue', 'Uno 1/2', u[:27]), shulker(3, 'blue', 'Uno 2/2', u[27:])]
    return 'give @p chest[custom_name="Card decks",container=[' + ','.join(boxes) + ']] 1'


if __name__ == '__main__':
    p, u = playing(), uno()
    # self-check: real deck sizes, no duplicate names, brackets balanced, fits a command block (32,500 chars)
    assert sum(c for _, c in p) == 54 and len({n for n, _ in p}) == 53, 'playing deck'
    assert sum(c for _, c in u) == 108 and len({n for n, _ in u}) == 54, 'uno deck'
    cmd = command()
    assert cmd.count('{') == cmd.count('}') and cmd.count('[') == cmd.count(']') and len(cmd) < 32500, len(cmd)
    print(cmd)
