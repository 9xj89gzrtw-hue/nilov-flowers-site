#!/usr/bin/env python3
"""S9 FAIL-CLOSED геометрия витрины (ДЕФЕКТ 1 «пляшущие карточки»).

Правила приёмки (по ТЗ S9):
  БЛОКИРУЮЩАЯ ОШИБКА, если в одном ряду сетки каталога:
  - высоты карточек различаются (>1px);
  - Y кнопки «В корзину» (и цены/названия/строки доверия) различаются (>0px
    для кнопки — «стоять ровно пиксель-в-пиксель»);
  - высота названия != 2 строки (2.7em);
  - плашек навигации != 4, или на мобайле (<768px) они не образуют сетку 2×2.

Запуск:  python3 scripts/check-card-geometry.py <geometry.json>
  geometry.json — дамп измерений из браузера (agent-browser eval):
  {viewport:{w,h}, scrollW, cards:[{top,left,w,h,priceY,nameY,metaY,ctaY,
  oneclickY,nameH,ctaH}], tiles:[{top,left,w,h,label}]}
Выход:  0 = OK (всё в струнку), 1 = БЛОКИРУЮЩАЯ ОШИБКА.
"""
import json
import sys


def fail(msgs, msg):
    msgs.append(msg)


def main():
    if len(sys.argv) != 2:
        print(__doc__, file=sys.stderr)
        sys.exit(2)
    with open(sys.argv[1], "r", encoding="utf-8") as fh:
        data = json.load(fh)

    errs = []
    vp = data.get("viewport", {})
    vw = int(vp.get("w", 0))
    cards = data.get("cards", [])
    tiles = data.get("tiles", [])
    scroll_w = int(data.get("scrollW", 0))

    # --- горизонтальный скролл не допустим на мобильном ---
    if vw and vw < 768 and scroll_w > vw:
        fail(errs, f"ГОРИЗОНТАЛЬНЫЙ СКРОЛЛ: scrollWidth={scroll_w} > viewport={vw}")

    # --- ряды карточек: группируем по top (клетки сетки одного ряда) ---
    rows = {}
    for c in cards:
        placed = False
        for key in rows:
            if abs(c["top"] - key) <= 4:
                rows[key].append(c)
                placed = True
                break
        if not placed:
            rows[c["top"]] = [c]

    for top, row in sorted(rows.items()):
        if len(row) < 2:
            continue  # одиночная карточка в ряду — не с чем сравнивать
        # высоты карточек ряда
        hs = [c["h"] for c in row]
        if max(hs) - min(hs) > 1:
            fail(errs, f"ряд@{top}: ВЫСОТЫ ПЛЯШУТ {hs} (разброс {max(hs)-min(hs)}px)")
        # Y-координаты строк — пиксель-в-пиксель
        for field, human in (("priceY", "цена"), ("nameY", "название"),
                             ("metaY", "строка доверия"), ("ctaY", "КНОПКА «В корзину»"),
                             ("oneclickY", "ссылка «В 1 клик»")):
            vals = [c[field] for c in row if c[field] is not None]
            if len(vals) < len(row):
                fail(errs, f"ряд@{top}: {human} — нет у части карточек ({vals})")
                continue
            spread = max(vals) - min(vals)
            if spread != 0:
                fail(errs, f"ряд@{top}: {human} стоит КРИВО — Y {vals}, разброс {spread}px (требование: 0px)")
        # высота названия = 2 строки
        for c in row:
            if c["nameH"] is not None and not (36 <= c["nameH"] <= 41):
                fail(errs, f"ряд@{top}: название карточки@{c['left']} высота {c['nameH']}px (ожидание 2 строки ≈38px)")

    # --- кнопка «В корзину»: высота 40px (44px тач) ---
    for c in cards:
        if c["ctaH"] is not None and c["ctaH"] > 46:
            fail(errs, f"кнопка карточки@{c['left']} раздута: {c['ctaH']}px (ожидание 40px)")

    # --- плашки навигации: РОВНО 4 ---
    if len(tiles) != 4:
        fail(errs, f"ПЛАШЕК {len(tiles)} — должно быть РОВНО 4 (пустых мест на витрине нет)")
    if len(tiles) == 4:
        labels = [t["label"] for t in tiles]
        tops = sorted(t["top"] for t in tiles)
        lefts = sorted(t["left"] for t in tiles)
        if vw and vw < 768:
            # сетка 2×2: два ряда по две плашки, ширины равны
            row_tops = sorted(set(tops))
            if len(row_tops) != 2:
                fail(errs, f"мобайл: плашки НЕ 2 ряда (tops={row_tops})")
            col_lefts = sorted(set(lefts))
            if len(col_lefts) != 2:
                fail(errs, f"мобайл: плашки НЕ 2 колонки (lefts={col_lefts})")
            ws = [t["w"] for t in tiles]
            if max(ws) - min(ws) > 1:
                fail(errs, f"мобайл: ширины плашек пляшут {ws}")
        print("  плашки: " + " | ".join(labels))

    print(f"  вьюпорт {vw}px, карточек {len(cards)}, рядов {len([k for k, v in rows.items() if v])}, плашек {len(tiles)}")
    if errs:
        print("\nБЛОКИРУЮЩИЕ ОШИБКИ ГЕОМЕТРИИ:")
        for e in errs:
            print("  ✗ " + e)
        sys.exit(1)
    print("ГЕОМЕТРИЯ OK — карточки и кнопки стоят в струнку (0px разброс)")
    sys.exit(0)


if __name__ == "__main__":
    main()
