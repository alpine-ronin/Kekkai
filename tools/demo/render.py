"""Renders the README's demo GIF and screenshots from real Kekkai runs.

Each scene is a list of shell commands run for real, in a fresh demo repository (tools/demo/setup.sh), with the
kekkai executable given on the command line; the output, ANSI colours included, is drawn in a terminal window in the
brand's colours. Nothing in the pictures is typed by hand but the commands.

    python3 tools/demo/render.py build/kekkai.phar        writes docs/assets/demo.gif and docs/assets/*.png

Needs Pillow and the JetBrains Mono fonts (KEKKAI_DEMO_FONTS: a directory holding JetBrainsMono-Regular.ttf and
JetBrainsMono-Bold.ttf).
"""

import os
import re
import shutil
import subprocess
import sys
import tempfile

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ASSETS = os.path.join(ROOT, "docs", "assets")
FONTS = os.environ.get("KEKKAI_DEMO_FONTS", "/home/claude/sandbox/fonts/jb/fonts/ttf")

INK = (18, 22, 28)
WINDOW = (24, 29, 37)
BAR = (33, 39, 49)
PAPER = (232, 231, 226)
MUTED = (128, 137, 150)
VERMILION = (240, 122, 98)
PALETTE = {
    30: (64, 72, 84), 31: VERMILION, 32: (126, 201, 138), 33: (232, 194, 104), 34: (143, 179, 240),
    35: (205, 152, 230), 36: (127, 212, 212), 37: PAPER, 90: MUTED, 91: VERMILION, 92: (126, 201, 138),
    93: (232, 194, 104), 94: (143, 179, 240), 95: (205, 152, 230), 96: (127, 212, 212), 97: (250, 250, 247),
}

SIZE = 15
COLS = 100
PAD = 22
BAR_H = 34

regular = ImageFont.truetype(os.path.join(FONTS, "JetBrainsMono-Regular.ttf"), SIZE)
bold = ImageFont.truetype(os.path.join(FONTS, "JetBrainsMono-Bold.ttf"), SIZE)
CHAR_W = regular.getlength("m")
LINE_H = int(SIZE * 1.5)
WIDTH = int(PAD * 2 + CHAR_W * COLS)


def parse(text):
    """ANSI text into lines of (char, fg, bg, bold) cells, wrapped at COLS."""
    lines, line = [], []
    fg, bg, strong = PAPER, None, False
    i = 0
    while i < len(text):
        if text[i] == "\x1b":
            match = re.match(r"\x1b\[([0-9;]*)m", text[i:])
            if match:
                codes = [int(c) for c in match.group(1).split(";") if c != ""] or [0]
                for code in codes:
                    if code == 0:
                        fg, bg, strong = PAPER, None, False
                    elif code == 1:
                        strong = True
                    elif code == 22:
                        strong = False
                    elif code == 39:
                        fg = PAPER
                    elif code == 49:
                        bg = None
                    elif code in PALETTE:
                        fg = PALETTE[code]
                    elif 40 <= code <= 47:
                        bg = PALETTE[code - 10]
                i += len(match.group(0))
                continue
            i += 1
            continue
        char = text[i]
        i += 1
        if char == "\n":
            lines.append(line)
            line = []
            continue
        if char == "\r":
            continue
        if len(line) >= COLS:
            lines.append(line)
            line = []
        line.append((char, fg, bg, strong))
    lines.append(line)
    return lines


def prompt(command):
    cells = [("$", VERMILION, None, True), (" ", PAPER, None, False)]
    for char in command:
        colour = MUTED if command.lstrip().startswith("#") else PAPER
        cells.append((char, colour, None, False))
    return cells


def draw(lines, rows, title="~/shop", cursor=False):
    height = BAR_H + PAD * 2 + LINE_H * rows
    image = Image.new("RGB", (WIDTH, height), INK)
    pen = ImageDraw.Draw(image)
    pen.rounded_rectangle((0, 0, WIDTH - 1, height - 1), radius=12, fill=WINDOW)
    pen.rounded_rectangle((0, 0, WIDTH - 1, BAR_H + 12), radius=12, fill=BAR)
    pen.rectangle((0, BAR_H, WIDTH - 1, BAR_H + 12), fill=WINDOW)
    for n, colour in enumerate([(240, 122, 98), (232, 194, 104), (126, 201, 138)]):
        x = 20 + n * 20
        pen.ellipse((x - 6, BAR_H / 2 - 6, x + 6, BAR_H / 2 + 6), fill=colour)
    pen.text((WIDTH / 2, BAR_H / 2), title, font=regular, fill=MUTED, anchor="mm")
    visible = lines[-rows:]
    for row, line in enumerate(visible):
        y = BAR_H + PAD + row * LINE_H
        for col, (char, fg, bg, strong) in enumerate(line):
            x = PAD + col * CHAR_W
            if bg is not None:
                pen.rectangle((x, y - 2, x + CHAR_W, y + LINE_H - 4), fill=bg)
            if char != " ":
                pen.text((x, y), char, font=bold if strong else regular, fill=fg)
    if cursor and visible:
        row = len(visible) - 1
        x = PAD + len(visible[-1]) * CHAR_W
        y = BAR_H + PAD + row * LINE_H
        pen.rectangle((x, y, x + CHAR_W - 1, y + LINE_H - 5), fill=PAPER)
    return image


def run(command, cwd, env):
    result = subprocess.run(["bash", "-c", command], cwd=cwd, env=env, capture_output=True, text=True)
    output = (result.stdout + result.stderr).rstrip("\n")
    return output.replace(env["HOME"], "~")


def setup(kekkai):
    work = tempfile.mkdtemp(prefix="kekkai-demo-")
    shop = os.path.join(work, "shop")
    bindir = os.path.join(work, "bin")
    os.makedirs(bindir)
    shutil.copy(kekkai, os.path.join(bindir, "kekkai"))
    os.chmod(os.path.join(bindir, "kekkai"), 0o755)
    env = dict(os.environ, PATH=bindir + ":" + os.environ["PATH"], COLUMNS=str(COLS), HOME=work, CLAUDE_PROJECT_DIR=shop, FORCE_COLOR="1")
    subprocess.run(["bash", os.path.join(ROOT, "tools", "demo", "setup.sh"), shop], check=True, env=env)
    return work, shop, env


def scene(commands, cwd, env):
    lines = []
    for command in commands:
        lines.append(prompt(command))
        if not command.lstrip().startswith("#"):
            output = run(command, cwd, env)
            if output:
                lines.extend(parse(output))
        lines.append([])
    return lines


def screenshot(name, commands, cwd, env, title="~/shop", cols=COLS):
    global COLS, WIDTH
    saved = COLS, WIDTH
    COLS, WIDTH = cols, int(PAD * 2 + CHAR_W * cols)
    env = dict(env, COLUMNS=str(cols))
    lines = scene(commands, cwd, env)
    while lines and lines[-1] == []:
        lines.pop()
    image = draw(lines, len(lines), title)
    image.save(os.path.join(ASSETS, name), optimize=True)
    COLS, WIDTH = saved
    print("wrote", name, image.size)


def gif(name, steps, cwd, env, rows=26):
    frames, durations = [], []
    lines = []

    def frame(duration, cursor=False):
        frames.append(draw(lines, rows, cursor=cursor))
        durations.append(duration)

    frame(900, cursor=True)
    for command, hold in steps:
        lines.append(prompt(""))
        typed = ""
        for n, char in enumerate(command):
            typed += char
            if n % 3 == 2 or n == len(command) - 1:
                lines[-1] = prompt(typed)
                frame(45, cursor=True)
        frame(350, cursor=True)
        if not command.lstrip().startswith("#"):
            output = run(command, cwd, env)
            if output:
                lines.extend(parse(output))
        lines.append([])
        frame(hold)
    frame(2500)

    palette_source = frames[-1].quantize(colors=64, method=Image.Quantize.MEDIANCUT)
    quantized = [f.quantize(palette=palette_source, dither=Image.Dither.NONE) for f in frames]
    quantized[0].save(os.path.join(ASSETS, name), save_all=True, append_images=quantized[1:], duration=durations, loop=0, optimize=True, disposal=1)
    print("wrote", name, frames[0].size, len(frames), "frames", sum(durations) / 1000, "s")


def main():
    kekkai = os.path.abspath(sys.argv[1])
    os.makedirs(ASSETS, exist_ok=True)

    work, shop, env = setup(kekkai)
    try:
        gif("demo.gif", [
            ("kekkai scope declare catalog -m \"Add a price to products\"", 1600),
            ("# Claude Code asks the hook before the agent edits the billing context", 600),
            ("cat edit.json", 1400),
            ("kekkai hook pre-tool-use < edit.json | jq -r '.[] | .permissionDecision, .permissionDecisionReason'", 4500),
            ("# it rewrites the invoice through the shell instead", 600),
            ("sed -i 's/total/amount/' src/Billing/Invoice.php", 600),
            ("kekkai check", 4200),
            ("kekkai scope widen src/Billing/Invoice.php -m \"Invoices show the price\"", 1600),
            ("kekkai check", 3500),
        ], shop, env)
    finally:
        shutil.rmtree(work)

    work, shop, env = setup(kekkai)
    try:
        run("kekkai scope declare catalog -m 'Add a price to products' && sed -i 's/total/amount/' src/Billing/Invoice.php && echo '<?php' > src/Catalog/Price.php && echo x >> composer.lock", shop, env)
        screenshot("check.png", ["kekkai check"], shop, env)
        screenshot("doctor.png", ["kekkai doctor"], shop, env)
        screenshot("help.png", ["kekkai list"], shop, env, title="kekkai", cols=120)
    finally:
        shutil.rmtree(work)


main()
