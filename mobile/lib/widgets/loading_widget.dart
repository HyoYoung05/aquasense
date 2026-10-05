import 'package:flutter/material.dart';

class LoadingWidget extends StatelessWidget {
  final String label;
  const LoadingWidget({super.key, this.label = 'Loading…'});
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: 20),
          Text(label, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}
