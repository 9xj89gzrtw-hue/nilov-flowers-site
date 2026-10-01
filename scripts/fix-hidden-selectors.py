#!/usr/bin/env python3
"""S4-финал: ремонт двойных повреждений '[h[hidden]' (первый blanket-replace
вставил '[hidden]' после 'h' исходного слова). Истину проверяем HEX-ом —
текстовый канал Bash искажает '[h' в отображении."""
import re, hashlib, json

PATH = 'css/five.css'
css = open(PATH, encoding='utf-8').read()

FIX = '[' + 'hidden]'          # literal, собранный конкатенацией
ESC = re.escape(FIX)

# -h[hidden] -> -hidden]  |  [h[hidden] -> [hidden]
css2, n1 = re.subn(r'([\-[])h' + ESC, r'\1' + 'hidden]', css)

open(PATH, 'w', encoding='utf-8').write(css2)

# ---- HEX-верификация (обход искажения канала) ----
raw = open(PATH, 'rb').read()
def h(s): return s.encode().hex()
report = {
    'replacements': n1,
    'md5': hashlib.md5(raw).hexdigest(),
    'citybar_clean': raw.count(b'[data-citybar-hidden]'),
    'cartPanel_not_clean': raw.count(b'#cartPanel:not(' + b'[hidden])'),
    'cartPanel_clean': raw.count(b'#cartPanel' + b'[hidden]'),
    'oneclick_clean': raw.count(b'.oneclick' + b'[hidden]'),
    'menu_clean': raw.count(b'.fc-city-menu' + b'[hidden]'),
    'stray_h_bracket': raw.count(b'h' + b'[hidden]'),
}
print(json.dumps(report))
