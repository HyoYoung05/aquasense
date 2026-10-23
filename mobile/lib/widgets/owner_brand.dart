import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../theme/app_theme.dart';

class OwnerBrand extends StatelessWidget {
  final bool light;
  final bool compact;
  final bool showPortalLabel;

  const OwnerBrand({
    super.key,
    this.light = false,
    this.compact = false,
    this.showPortalLabel = false,
  });

  @override
  Widget build(BuildContext context) {
    final foreground = light ? Colors.white : forest;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: compact ? 32 : 38,
          height: compact ? 36 : 42,
          decoration: BoxDecoration(
            color: light ? const Color(0xFF28615A) : emerald,
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(
            Icons.water_drop_outlined,
            color: Colors.white,
            size: compact ? 21 : 25,
          ),
        ),
        const SizedBox(width: 10),
        Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text.rich(
              TextSpan(
                children: [
                  TextSpan(text: appName.replaceAll('+', '')),
                  TextSpan(
                    text: '+',
                    style: TextStyle(color: light ? mint : emerald),
                  ),
                ],
              ),
              style: TextStyle(
                color: foreground,
                fontSize: compact ? 18 : 21,
                fontWeight: FontWeight.w800,
                letterSpacing: -.7,
              ),
            ),
            if (showPortalLabel)
              Text(
                'KARINDERYA OWNER PORTAL',
                style: TextStyle(
                  color: light ? const Color(0xFFACC6BD) : muted,
                  fontSize: 8,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.1,
                ),
              ),
          ],
        ),
      ],
    );
  }
}
