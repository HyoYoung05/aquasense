const _months = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
];

String readingTime(DateTime utc) {
  final local = utc.toLocal();
  final hour = local.hour % 12 == 0 ? 12 : local.hour % 12;
  final minute = local.minute.toString().padLeft(2, '0');
  final period = local.hour < 12 ? 'AM' : 'PM';
  return '${_months[local.month - 1]} ${local.day}, ${local.year} '
      '$hour:$minute $period';
}

String cleanNumber(double value, {int decimals = 1}) {
  if (value == value.roundToDouble()) return value.toInt().toString();
  return value.toStringAsFixed(decimals);
}

String chartTime(DateTime utc, {required bool multiDay}) {
  final local = utc.toLocal();
  if (multiDay) return '${_months[local.month - 1]} ${local.day}';
  return '${local.hour.toString().padLeft(2, '0')}:'
      '${local.minute.toString().padLeft(2, '0')}';
}

String relativeTime(DateTime utc, {DateTime? now}) {
  final difference = (now ?? DateTime.now()).difference(utc.toLocal());
  if (difference.isNegative || difference.inSeconds < 10) return 'just now';
  if (difference.inMinutes < 1) return '${difference.inSeconds} seconds ago';
  if (difference.inHours < 1) {
    return '${difference.inMinutes} minute${difference.inMinutes == 1 ? '' : 's'} ago';
  }
  if (difference.inDays < 1) {
    return '${difference.inHours} hour${difference.inHours == 1 ? '' : 's'} ago';
  }
  return '${difference.inDays} day${difference.inDays == 1 ? '' : 's'} ago';
}
