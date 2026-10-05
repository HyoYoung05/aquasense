import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class SensorCard extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const SensorCard({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
  });
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: const Color(0xFFD9E7DD)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: emerald),
        const SizedBox(height: 12),
        Text(label, style: const TextStyle(fontSize: 15)),
        const SizedBox(height: 6),
        Text(
          value,
          style: const TextStyle(
            color: forest,
            fontSize: 28,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}
