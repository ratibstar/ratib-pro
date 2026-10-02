/// Login background: promo phrases drift in random directions, bounce off the edges and
/// change colors on each bounce, drawn raised (emboss) with a fading reflection.
/// Same file in ratib_hr_mobile and rateb_mobile.
library;

import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

class PromoBackdrop extends StatefulWidget {
  const PromoBackdrop({super.key, required this.arabic, this.count = 7});

  final bool arabic;
  final int count;

  static const List<String> phrasesAr = [
    'برمجيات رتب لتقنية المعلومات',
    'نظام ERP سعودي متكامل',
    'الموارد البشرية والرواتب بلمسة',
    'حضور وانصراف ذكي',
    'فوترة إلكترونية معتمدة',
    'محاسبة ومخزون في مكان واحد',
    'تقارير لحظية لكل فرع',
    'أمان بمعايير المؤسسات',
    'يعمل حتى بدون إنترنت',
    'rateb.sa',
  ];

  static const List<String> phrasesEn = [
    'Rateb Software for IT',
    'One Saudi ERP for everything',
    'HR & payroll at your fingertips',
    'Smart attendance',
    'ZATCA-ready e-invoicing',
    'Accounting & inventory, unified',
    'Real-time branch reports',
    'Enterprise-grade security',
    'Works offline',
    'rateb.sa',
  ];

  @override
  State<PromoBackdrop> createState() => _PromoBackdropState();
}

const List<List<Color>> _palette = [
  [Color(0xFF38BDF8), Color(0xFF6366F1)],
  [Color(0xFF34D399), Color(0xFF22D3EE)],
  [Color(0xFFA78BFA), Color(0xFFF472B6)],
  [Color(0xFFFB7185), Color(0xFFF59E0B)],
  [Color(0xFFFBBF24), Color(0xFFF472B6)],
  [Color(0xFF22D3EE), Color(0xFF818CF8)],
  [Color(0xFF4ADE80), Color(0xFFA3E635)],
  [Color(0xFFF97316), Color(0xFFFACC15)],
];

class _Drifter {
  _Drifter(this.text, this.fontSize);

  final String text;
  final double fontSize;
  double x = 0;
  double y = 0;
  double vx = 0;
  double vy = 0;
  double phase = 0;
  List<Color> colors = _palette.first;
  TextPainter? face;
  TextPainter? dark;
  TextPainter? light;
  TextPainter? mirror;
  Size size = Size.zero;
}

class _PromoBackdropState extends State<PromoBackdrop>
    with SingleTickerProviderStateMixin {
  final math.Random _rnd = math.Random();
  final ValueNotifier<int> _frame = ValueNotifier<int>(0);
  late final Ticker _ticker;
  List<_Drifter> _items = const [];
  Size _area = Size.zero;
  Duration _last = Duration.zero;
  bool? _builtArabic;

  @override
  void initState() {
    super.initState();
    _ticker = createTicker(_tick)..start();
  }

  @override
  void dispose() {
    _ticker.dispose();
    _frame.dispose();
    super.dispose();
  }

  void _setup(Size area) {
    final source = List<String>.of(
        widget.arabic ? PromoBackdrop.phrasesAr : PromoBackdrop.phrasesEn);
    final first = source.removeAt(0);
    source.shuffle(_rnd);
    final picked = [first, ...source.take(widget.count - 1)];
    _items = [
      for (final text in picked)
        _Drifter(text, 15 + _rnd.nextDouble() * 8)
          ..phase = _rnd.nextDouble() * math.pi * 2,
    ];
    for (final it in _items) {
      _recolor(it);
      final angle = _rnd.nextDouble() * math.pi * 2;
      final speed = 16 + _rnd.nextDouble() * 26;
      it.vx = math.cos(angle) * speed;
      it.vy = math.sin(angle) * speed;
    }
    _scatter(area);
    _builtArabic = widget.arabic;
  }

  /// Spreads the phrases in vertical bands so they never start stacked together.
  void _scatter(Size area) {
    final bands = _items.length;
    for (var i = 0; i < bands; i++) {
      final it = _items[i];
      final maxX = math.max(1.0, area.width - it.size.width);
      final maxY = math.max(1.0, area.height - it.size.height * 2);
      it.x = _rnd.nextDouble() * maxX;
      it.y = maxY * (i + _rnd.nextDouble()) / bands;
    }
  }

  TextPainter _painter(String text, TextStyle style) {
    return TextPainter(
      text: TextSpan(text: text, style: style),
      textDirection: widget.arabic ? TextDirection.rtl : TextDirection.ltr,
    )..layout();
  }

  void _recolor(_Drifter it) {
    it.colors = _palette[_rnd.nextInt(_palette.length)];
    final base = TextStyle(
        fontSize: it.fontSize, fontWeight: FontWeight.w900, height: 1.1);
    final probe = _painter(it.text, base);
    it.size = probe.size;
    final rect = Offset.zero & it.size;
    it.face = _painter(
      it.text,
      base.copyWith(
        foreground: Paint()
          ..shader = LinearGradient(
            colors: [for (final c in it.colors) c.withValues(alpha: 0.48)],
          ).createShader(rect),
        shadows: [
          Shadow(color: it.colors.first.withValues(alpha: 0.4), blurRadius: 14)
        ],
      ),
    );
    it.dark = _painter(
        it.text, base.copyWith(color: Colors.black.withValues(alpha: 0.35)));
    it.light = _painter(
        it.text, base.copyWith(color: Colors.white.withValues(alpha: 0.15)));
    it.mirror = _painter(
      it.text,
      base.copyWith(
        foreground: Paint()
          ..shader = LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.transparent,
              it.colors.last.withValues(alpha: 0.25)
            ],
          ).createShader(rect),
      ),
    );
  }

  void _tick(Duration elapsed) {
    final dt = _last == Duration.zero
        ? 0.0
        : math.min(0.05, (elapsed - _last).inMicroseconds / 1e6);
    _last = elapsed;
    if (_area.isEmpty || _items.isEmpty) return;
    final w = _area.width;
    final h = _area.height;
    for (final it in _items) {
      final turn = (_rnd.nextDouble() - 0.5) * 1.2 * dt;
      final vx = it.vx * math.cos(turn) - it.vy * math.sin(turn);
      it.vy = it.vx * math.sin(turn) + it.vy * math.cos(turn);
      it.vx = vx;
      it.x += it.vx * dt;
      it.y += it.vy * dt;
      it.phase += dt * 1.1;
      final maxX = math.max(0.0, w - it.size.width);
      final maxY = math.max(0.0, h - it.size.height * 2);
      var hit = false;
      if (it.x < 0) {
        it.x = 0;
        it.vx = it.vx.abs();
        hit = true;
      } else if (it.x > maxX) {
        it.x = maxX;
        it.vx = -it.vx.abs();
        hit = true;
      }
      if (it.y < 0) {
        it.y = 0;
        it.vy = it.vy.abs();
        hit = true;
      } else if (it.y > maxY) {
        it.y = maxY;
        it.vy = -it.vy.abs();
        hit = true;
      }
      if (hit) _recolor(it);
    }
    _frame.value++;
  }

  @override
  Widget build(BuildContext context) {
    final still = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (still != !_ticker.isActive) {
      still ? _ticker.stop() : _ticker.start();
    }
    return IgnorePointer(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final area = constraints.biggest;
          if (_items.isEmpty || _builtArabic != widget.arabic) {
            _setup(area);
          } else if (area.width > _area.width * 1.2 ||
              area.height > _area.height * 1.2) {
            _scatter(area);
          }
          _area = area;
          return RepaintBoundary(
            child: CustomPaint(
              size: area,
              painter: _PromoPainter(_items, _frame),
            ),
          );
        },
      ),
    );
  }
}

class _PromoPainter extends CustomPainter {
  _PromoPainter(this.items, Listenable frame) : super(repaint: frame);

  final List<_Drifter> items;

  @override
  void paint(Canvas canvas, Size size) {
    for (final it in items) {
      final face = it.face;
      if (face == null) continue;
      final pulse = 1 + 0.05 * math.sin(it.phase);
      final h = it.size.height;
      canvas.save();
      canvas.translate(it.x + it.size.width / 2, it.y + h / 2);
      canvas.scale(pulse);
      canvas.translate(-it.size.width / 2, -h / 2);
      it.dark!.paint(canvas, const Offset(1.5, 1.5));
      it.light!.paint(canvas, const Offset(-1, -1));
      face.paint(canvas, Offset.zero);
      canvas.save();
      canvas.translate(0, h * 2 - 4);
      canvas.scale(1, -1);
      it.mirror!.paint(canvas, Offset.zero);
      canvas.restore();
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_PromoPainter oldDelegate) => oldDelegate.items != items;
}
