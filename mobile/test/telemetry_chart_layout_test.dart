import 'dart:io';
import 'dart:ui' as ui;

import 'package:aquasense_mobile/models/monitoring.dart';
import 'package:aquasense_mobile/theme/app_theme.dart';
import 'package:aquasense_mobile/widgets/telemetry_chart.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

TelemetryHistory history({bool single = false}) => TelemetryHistory(
  trapId: 1,
  trapName: 'Assigned trap',
  businessName: 'Owner shop',
  range: HistoryRange.last7Days,
  from: DateTime.utc(2026, 11, 6),
  to: DateTime.utc(2026, 11, 13),
  page: 1,
  pages: 1,
  total: single ? 1 : 10,
  readings: List.generate(
    single ? 1 : 10,
    (i) => TelemetryPoint(
      deviceCode: 'AQS-001',
      wasteLevel: i == 4 ? null : 58 + i.toDouble(),
      ultrasonicDistance: 12.4,
      temperature: 31.5,
      turbidity: null,
      turbidityRawAdc: 1800,
      flowRate: 0,
      flowPulseCount: 12500000,
      gasValue: null,
      odorValue: null,
      gasRawAdc: 500,
      condition: 'NORMAL',
      isSimulated: false,
      isTest: false,
      recordedAt: DateTime.utc(2026, 11, 13).subtract(Duration(hours: i * 12)),
    ),
  ),
);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    if (Platform.isWindows) {
      final font = File('${Platform.environment['WINDIR']}/Fonts/segoeui.ttf');
      if (await font.exists()) {
        final loader = FontLoader('Segoe UI')
          ..addFont(
            Future.value(ByteData.sublistView(await font.readAsBytes())),
          );
        await loader.load();
      }
    }
  });
  for (final width in [280.0, 680.0]) {
    testWidgets('chart labels fit $width width with large text', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width + 40, 700);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final boundary = GlobalKey();
      await tester.pumpWidget(
        MaterialApp(
          theme: ownerTheme(),
          home: Scaffold(
            body: MediaQuery(
              data: MediaQueryData(
                size: Size(width + 40, 700),
                textScaler: TextScaler.linear(1.6),
              ),
              child: Center(
                child: SizedBox(
                  width: width,
                  child: RepaintBoundary(
                    key: boundary,
                    child: ColoredBox(
                      color: Colors.white,
                      child: TelemetryChart(
                        history: history(),
                        metric: SensorMetric.wasteLevel,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      final frame = tester.getRect(find.byType(TelemetryChart));
      for (final label
          in find
              .descendant(
                of: find.byType(TelemetryChart),
                matching: find.byType(Text),
              )
              .evaluate()) {
        final rect = tester.getRect(find.byWidget(label.widget));
        expect(rect.left, greaterThanOrEqualTo(frame.left - 1));
        expect(rect.right, lessThanOrEqualTo(frame.right + 1));
        expect(rect.bottom, lessThanOrEqualTo(frame.bottom + 1));
      }
      final chart = tester.widget<LineChart>(find.byType(LineChart));
      expect(
        chart.data.lineBarsData.single.spots
            .where((spot) => spot.isNull())
            .length,
        1,
      );
      expect(chart.data.maxY, 100);
      final plotHeight = tester
          .getSize(find.byKey(const Key('telemetry-plot')))
          .height;
      expect(plotHeight, width < 360 ? 240 : 340);
      // Render previews for visual QA; build output stays outside source control.
      final render =
          boundary.currentContext!.findRenderObject() as RenderRepaintBoundary;
      await tester.runAsync(() async {
        final image = await render.toImage(pixelRatio: 2);
        final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
        final output = File('build/chart-qa/chart-${width.toInt()}.png');
        await output.parent.create(recursive: true);
        await output.writeAsBytes(bytes!.buffer.asUint8List());
        image.dispose();
      });
    });
  }

  testWidgets('single point and large flow values remain readable', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SizedBox(
            width: 280,
            child: TelemetryChart(
              history: history(single: true),
              metric: SensorMetric.flowPulseCount,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    final chart = tester.widget<LineChart>(find.byType(LineChart));
    expect(chart.data.maxX, 1);
    expect(chart.data.lineBarsData.single.spots.single.y, 12500000);
    expect(find.textContaining('M'), findsWidgets);
  });
}
