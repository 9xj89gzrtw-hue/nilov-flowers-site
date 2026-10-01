#!/usr/bin/env python3
"""S3 (v2026.3): минификатор CSS — whitespace + комментарии only.
Селекторы/значения не меняются (паттерн e2min.php). Запуск: python3 scripts/minify-css.py
mtime min всегда новее исходника (head.php отдаёт min, когда он свежее)."""
import re, os, sys

def minify(src):
    css = re.sub(r'/\*.*?\*/', '', src, flags=re.S)      # комментарии
    css = re.sub(r'\s+', ' ', css)                       # пробелы
    css = re.sub(r'\s*([{};:,>])\s*', r'\1', css)         # вокруг синтаксиса
    css = css.replace(';}', '}')
    return css.strip()

for name in ['style.css', 'nilov.css', 'five.css', 'motion-w104.css', 'fonts.css', 'category.css', 'secondary.css', 'product-extras.css']:
    p = os.path.join('css', name)
    if not os.path.isfile(p):
        continue
    with open(p) as f:
        src = f.read()
    out = os.path.join('css', name.replace('.css', '.min.css'))
    with open(out, 'w') as f:
        f.write(minify(src))
    os.utime(out)  # mtime min = сейчас > исходника
    print(f"{name}: {len(src)//1024}KB -> {os.path.getsize(out)//1024}KB")
