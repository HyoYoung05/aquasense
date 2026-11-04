import 'package:aquasense_mobile/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  for (final brightness in Brightness.values) {
    testWidgets('Chip labels remain readable with phone mode $brightness', (
      tester,
    ) async {
      tester.platformDispatcher.platformBrightnessTestValue = brightness;
      addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
      await tester.pumpWidget(
        MaterialApp(
          theme: ownerTheme(),
          home: Scaffold(
            body: Wrap(
              children: [
                const Chip(label: Text('CRITICAL')),
                ChoiceChip(
                  label: const Text('Today'),
                  selected: true,
                  onSelected: (_) {},
                ),
                ChoiceChip(
                  label: const Text('Last Hour'),
                  selected: false,
                  onSelected: (_) {},
                ),
              ],
            ),
          ),
        ),
      );
      for (final entry in {
        'CRITICAL': forest,
        'Today': Colors.white,
        'Last Hour': forest,
      }.entries) {
        final paragraph = tester.renderObject<RenderParagraph>(
          find.descendant(
            of: find.text(entry.key),
            matching: find.byType(RichText),
          ),
        );
        Color? renderedColor;
        paragraph.text.visitChildren((span) {
          renderedColor ??= span.style?.color;
          return true;
        });
        expect(renderedColor, entry.value);
      }
    });
  }
}
