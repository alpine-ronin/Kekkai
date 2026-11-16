"""Writes docs/assets/how-it-works.svg and how-it-works-dark.svg: the four steps of a task under Kekkai."""

import os

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
OUT = os.path.join(ROOT, "docs", "assets")

THEMES = {
    "how-it-works.svg": {"bg": "#F6F5F1", "card": "#FFFFFF", "ink": "#12161C", "muted": "#5B6270", "line": "#D9D6CE", "accent": "#C23B22", "soft": "#F7E3DE"},
    "how-it-works-dark.svg": {"bg": "#12161C", "card": "#1A2029", "ink": "#F6F5F1", "muted": "#9AA2AF", "line": "#2E3542", "accent": "#F07A62", "soft": "#2B1F1E"},
}

STEPS = [
    ("1", "Declare", "declare_scope", ["The agent says what the", "task is for and the paths", "or zones it will change."]),
    ("2", "Guard", "PreToolUse hook", ["Edit, Write outside the", "scope: refused, with the", "scope and what to do."]),
    ("3", "Widen", "widen_scope", ["Needs more? It widens,", "with a reason the", "reviewer sees."]),
    ("4", "Review", "Stop hook", ["The git diff, shell writes", "included, and your checks.", "Fails: back to work, once."]),
]

FONT = "ui-sans-serif, -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif"
MONO = "ui-monospace, 'JetBrains Mono', SFMono-Regular, Menlo, Consolas, monospace"


def svg(t):
    width, height = 1000, 300
    card_w, card_h, gap, top = 214, 200, 28, 52
    left = (width - (4 * card_w + 3 * gap)) / 2
    parts = [
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}" role="img" aria-label="How Kekkai works: declare, guard, widen, review">',
        f'<rect width="{width}" height="{height}" rx="16" fill="{t["bg"]}"/>',
        f'<text x="{width / 2}" y="32" text-anchor="middle" font-family="{FONT}" font-size="15" fill="{t["muted"]}">One task under Kekkai</text>',
    ]
    for i, (number, title, tool, lines) in enumerate(STEPS):
        x = left + i * (card_w + gap)
        parts.append(f'<rect x="{x}" y="{top}" width="{card_w}" height="{card_h}" rx="14" fill="{t["card"]}" stroke="{t["line"]}" stroke-width="1.5"/>')
        parts.append(f'<circle cx="{x + 30}" cy="{top + 32}" r="14" fill="{t["soft"]}" stroke="{t["accent"]}" stroke-width="2"/>')
        parts.append(f'<text x="{x + 30}" y="{top + 37}" text-anchor="middle" font-family="{FONT}" font-size="14" font-weight="700" fill="{t["accent"]}">{number}</text>')
        parts.append(f'<text x="{x + 54}" y="{top + 38}" font-family="{FONT}" font-size="18" font-weight="700" fill="{t["ink"]}">{title}</text>')
        parts.append(f'<text x="{x + 18}" y="{top + 76}" font-family="{MONO}" font-size="13" fill="{t["accent"]}">{tool}</text>')
        for n, line in enumerate(lines):
            parts.append(f'<text x="{x + 18}" y="{top + 108 + n * 22}" font-family="{FONT}" font-size="14" fill="{t["muted"]}">{line}</text>')
        if i < 3:
            ax = x + card_w + 6
            ay = top + card_h / 2
            parts.append(f'<path d="M{ax} {ay}h{gap - 12}m-6 -6l6 6l-6 6" fill="none" stroke="{t["accent"]}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>')
    parts.append(f'<text x="{width / 2}" y="{top + card_h + 32}" text-anchor="middle" font-family="{FONT}" font-size="14" fill="{t["muted"]}">Edits in the scope get no answer from Kekkai: your own Claude Code permissions apply as before.</text>')
    parts.append("</svg>\n")
    return "".join(parts)


os.makedirs(OUT, exist_ok=True)
for name, theme in THEMES.items():
    with open(os.path.join(OUT, name), "w") as handle:
        handle.write(svg(theme))
    print("wrote", name)
