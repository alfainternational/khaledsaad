import 'package:flutter/material.dart';

/// شعار المنصة — صورة مربّعة بحواف دائرية، مع بديل احتياطي إن تعذّر تحميل الأصل.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.size = 56});

  final double size;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(size * 0.28);
    return ClipRRect(
      borderRadius: radius,
      child: Image.asset(
        'assets/brand/icon-app.png',
        width: size,
        height: size,
        fit: BoxFit.cover,
        semanticLabel: 'شعار المنصة',
        errorBuilder: (context, error, stackTrace) {
          final scheme = Theme.of(context).colorScheme;
          return Container(
            width: size,
            height: size,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: scheme.primaryContainer,
              borderRadius: radius,
            ),
            child: Icon(
              Icons.trending_up_rounded,
              size: size * 0.55,
              color: scheme.onPrimaryContainer,
            ),
          );
        },
      ),
    );
  }
}
