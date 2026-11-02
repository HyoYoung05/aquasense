String? validateEmail(String? value) {
  final email = value?.trim() ?? '';
  if (email.isEmpty) return 'Enter your email address.';
  if (email.length > 190 ||
      !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email)) {
    return 'Enter a valid email address.';
  }
  return null;
}

String? validateLoginIdentifier(String? value) {
  final identifier = value?.trim() ?? '';
  if (identifier.isEmpty) return 'Enter your email address or contact number.';
  if (identifier.length > 190) {
    return 'Enter a valid email address or contact number.';
  }
  final isEmail = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(identifier);
  final normalizedContact = identifier.replaceAll(RegExp(r'[().\-\s]'), '');
  final isContact = RegExp(r'^\+?[0-9]{7,20}$').hasMatch(normalizedContact);
  return isEmail || isContact
      ? null
      : 'Enter a valid email address or contact number.';
}

String? validatePassword(String? value) =>
    value == null || value.isEmpty ? 'Enter your password.' : null;
