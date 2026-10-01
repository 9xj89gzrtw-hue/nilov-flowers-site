#!/usr/bin/env python3
"""S8 FAIL-CLOSED пиксель-контроль .card-media (инцидент «серые прямоугольники»).

Правила (по ТЗ):
  БЛОКИРУЮЩАЯ ОШИБКА, если в области фото букета:
  - средний цвет ≈ #F0F0F0 / #F7F7F8 (пустая подложка) ИЛИ
  - дисперсия цветов близка к нулю (однотонная плашка без фото).

Запуск:  python3 scripts/check-card-media.py <screenshot.png> <boxes.json>
  boxes.json — массив [{x,y,w,h}] в координатах ПОЛНОГО скриншота (страницы).
Выход:    0 = OK (цветы видны), 1 = БЛОКИРУЮЩАЯ ОШИБКА (серо/плоско).
"""
import json
import sys

try:
    from PIL import Image
except ImportError:
    print("FATAL: PIL недоступен — pip install pillow", file=sys.stderr)
    sys.exit(2)


def analyze_box(img, box, inset=6):
    x, y, w, h = box["x"], box["y"], box["w"], box["h"]
    # inset сжимает рамку внутрь: не берём контур 1px и углы скругления
    crop = img.crop((x + inset, y + inset, x + w - inset, y + h - inset))
    small = crop.resize((40, 40))  # усреднение, быстрая статистика
    pixels = list(small.getdata())
    n = len(pixels)
    rs = sum(p[0] for p in pixels) / n
    gs = sum(p[1] for p in pixels) / n
    bs = sum(p[2] for p in pixels) / n
    # дисперсия по каналам (яркостная)
    lum = [0.299 * p[0] + 0.587 * p[1] + 0.114 * p[2] for p in pixels]
    mean_lum = sum(lum) / n
    var = sum((l - mean_lum) ** 2 for l in lum) / n
    std = var ** 0.5
    return rs, gs, bs, std


def is_placeholder(r, g, b, std):
    # серые подложки: #F0F0F0=(240,240,240), #F7F7F8=(247,247,248)
    near_gray = (
        abs(r - g) < 6 and abs(g - b) < 6 and abs(r - b) < 6
        and 225 <= (r + g + b) / 3 <= 252
    )
    flat = std < 6.0  # почти нулевая вариация = однотонная плашка
    return near_gray or flat


def main():
    if len(sys.argv) != 3:
        print(__doc__, file=sys.stderr)
        sys.exit(2)
    shot, boxes_file = sys.argv[1], sys.argv[2]
    with open(boxes_file, "r", encoding="utf-8") as fh:
        boxes = json.load(fh)
    img = Image.open(shot).convert("RGB")
    fails = []
    print(f"Проверяю {len(boxes)} областей .card-media в {shot} "
          f"({img.width}x{img.height})")
    for i, box in enumerate(boxes):
        r, g, b, std = analyze_box(img, box)
        bad = is_placeholder(r, g, b, std)
        verdict = "FAIL ⛔ СЕРО/ПЛОСКО" if bad else "ok 🌸"
        print(f"  [{i:02d}] box=({box['x']},{box['y']},{box['w']}x{box['h']}) "
              f"avg=({r:.0f},{g:.0f},{b:.0f}) std={std:.1f} → {verdict}")
        if bad:
            fails.append(i)
    if fails:
        print(f"\nБЛОКИРУЮЩАЯ ОШИБКА: {len(fails)}/{len(boxes)} карточек — "
              f"СЕРЫЕ ПРЯМОУГОЛЬНИКИ без фото (индексы: {fails}).")
        print("PUSH ЗАПРЕЩЁН. Проверить пути/наличие/загрузку изображений.")
        sys.exit(1)
    print(f"\nOK: {len(boxes)}/{len(boxes)} областей содержат реальные "
          f"фото (цвет/дисперсия живые). Push разрешён.")
    sys.exit(0)


if __name__ == "__main__":
    main()
