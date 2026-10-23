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
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(11),
      border: Border.all(color: line),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: emerald),
        const SizedBox(height: 12),
        Text(label, style: const TextStyle(color: muted, fontSize: 13)),
        const SizedBox(height: 6),
        Text(
          value,
          style: const TextStyle(
            color: forest,
            fontSize: 25,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}
