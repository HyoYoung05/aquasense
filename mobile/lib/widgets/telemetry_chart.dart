import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../models/monitoring.dart';
import '../theme/app_theme.dart';
import '../utils/formatters.dart';

/// Uses the available card width; axis labels stay within the plot edges.
class TelemetryChart extends StatelessWidget {
  final TelemetryHistory history;
  final SensorMetric metric;
  const TelemetryChart({
    super.key,
    required this.history,
    required this.metric,
  });

  String _axisNumber(double value) {
    if (value.abs() >= 1000000) return '${cleanNumber(value / 1000000)}M';
    if (value.abs() >= 1000) return '${cleanNumber(value / 1000)}k';
    return cleanNumber(value);
  }

  @override
  Widget build(BuildContext context) {
    if (!history.readings.any((point) => metric.valueOf(point) != null)) {
      return Padding(
        padding: const EdgeInsets.all(12),
        child: Text(
          'No ${metric.label.toLowerCase()} readings were received for this range.',
        ),
      );
    }
    final points = history.readings.reversed.toList(growable: false);
    final spots = [
      for (var index = 0; index < points.length; index++)
        metric.valueOf(points[index]) == null
            ? FlSpot.nullSpot
            : FlSpot(index.toDouble(), metric.valueOf(points[index])!),
    ];
    final values = points.map(metric.valueOf).whereType<double>().toList();
    final low = values.reduce((a, b) => a < b ? a : b);
    final high = values.reduce((a, b) => a > b ? a : b);
    final span = high - low;
    final padding = span > 0
        ? span * .1
        : (high.abs() * .05).clamp(1.0, double.infinity);
    final bottom = low >= 0 && low - padding < 0 ? 0.0 : low - padding;
    final multiDay = const {
      HistoryRange.last7Days,
      HistoryRange.last30Days,
      HistoryRange.custom,
    }.contains(history.range);
    return Semantics(
      label: '${metric.label} history chart with actual backend readings',
      child: LayoutBuilder(
        builder: (context, constraints) {
          final narrow = constraints.maxWidth < 360;
          final labels = <int>{
            0,
            if (!narrow) (points.length - 1) ~/ 2,
            points.length - 1,
          };
          final height = (constraints.maxWidth * .65).clamp(240.0, 340.0);
          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                '${metric.label} (${metric.unit})',
                style: const TextStyle(
                  color: forest,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 12),
              // Keep the plot legible at large system text sizes without squeezing
              // the graph; the heading and surrounding controls retain full scaling.
              MediaQuery.withClampedTextScaling(
                minScaleFactor: 1,
                maxScaleFactor: 1.3,
                child: SizedBox(
                  key: const Key('telemetry-plot'),
                  height: height,
                  child: Padding(
                    padding: const EdgeInsets.only(top: 8, right: 8),
                    child: LineChart(
                      LineChartData(
                        minX: 0,
                        maxX: points.length <= 1
                            ? 1
                            : (points.length - 1).toDouble(),
                        minY: metric == SensorMetric.wasteLevel ? 0 : bottom,
                        maxY: metric == SensorMetric.wasteLevel
                            ? 100
                            : high + padding,
                        gridData: FlGridData(
                          drawVerticalLine: false,
                          getDrawingHorizontalLine: (_) => const FlLine(
                            color: line,
                            strokeWidth: 1,
                            dashArray: [4, 4],
                          ),
                        ),
                        borderData: FlBorderData(
                          show: true,
                          border: const Border(
                            left: BorderSide(color: line),
                            bottom: BorderSide(color: line),
                          ),
                        ),
                        lineTouchData: LineTouchData(
                          touchTooltipData: LineTouchTooltipData(
                            maxContentWidth: 190,
                            getTooltipColor: (_) => forest,
                            fitInsideHorizontally: true,
                            fitInsideVertically: true,
                            getTooltipItems: (touched) => touched
                                .map(
                                  (spot) => LineTooltipItem(
                                    '${cleanNumber(spot.y)} ${metric.unit}\n${readingTime(points[spot.x.round()].recordedAt)}',
                                    const TextStyle(
                                      color: Colors.white,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                )
                                .toList(),
                          ),
                        ),
                        titlesData: FlTitlesData(
                          topTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          rightTitles: const AxisTitles(
                            sideTitles: SideTitles(showTitles: false),
                          ),
                          leftTitles: AxisTitles(
                            sideTitles: SideTitles(
                              showTitles: true,
                              reservedSize: 48,
                              interval: metric == SensorMetric.wasteLevel
                                  ? 25
                                  : null,
                              // Auto-scaled end points often sit beside a regular
                              // tick. Only show regular ticks to avoid overlapping.
                              minIncluded: metric == SensorMetric.wasteLevel,
                              maxIncluded: metric == SensorMetric.wasteLevel,
                              getTitlesWidget: (value, meta) => SideTitleWidget(
                                meta: meta,
                                space: 6,
                                fitInside: SideTitleFitInsideData.fromTitleMeta(
                                  meta,
                                  distanceFromEdge: 0,
                                ),
                                child: Text(
                                  _axisNumber(value),
                                  maxLines: 1,
                                  style: const TextStyle(
                                    color: forest,
                                    fontSize: 11,
                                  ),
                                ),
                              ),
                            ),
                          ),
                          bottomTitles: AxisTitles(
                            sideTitles: SideTitles(
                              showTitles: true,
                              reservedSize: multiDay ? 48 : 34,
                              interval: 1,
                              getTitlesWidget: (value, meta) {
                                final index = value.round();
                                if ((value - index).abs() > .001 ||
                                    !labels.contains(index) ||
                                    index >= points.length) {
                                  return const SizedBox.shrink();
                                }
                                final time = points[index].recordedAt;
                                return SideTitleWidget(
                                  meta: meta,
                                  space: 10,
                                  fitInside:
                                      SideTitleFitInsideData.fromTitleMeta(
                                        meta,
                                        distanceFromEdge: 2,
                                      ),
                                  child: Text(
                                    multiDay
                                        ? '${chartTime(time, multiDay: true)}\n${chartTime(time, multiDay: false)}'
                                        : chartTime(time, multiDay: false),
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      color: forest,
                                      fontSize: 11,
                                      height: 1.2,
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        ),
                        lineBarsData: [
                          LineChartBarData(
                            spots: spots,
                            color: emerald,
                            barWidth: 2.5,
                            isCurved: false,
                            dotData: FlDotData(
                              getDotPainter: (spot, percent, bar, index) =>
                                  FlDotCirclePainter(
                                    radius: 2.5,
                                    color: emerald,
                                    strokeWidth: 0,
                                  ),
                            ),
                            belowBarData: BarAreaData(
                              show: true,
                              color: mint.withValues(alpha: .3),
                            ),
                          ),
                        ],
                      ),
                      duration: const Duration(milliseconds: 250),
                    ),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
