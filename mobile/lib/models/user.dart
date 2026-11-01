class OwnerUser {
  final int id;
  final String fullName, email;
  final bool hasProfilePhoto;
  final DateTime? profilePhotoUpdatedAt;

  const OwnerUser({
    required this.id,
    required this.fullName,
    required this.email,
    this.hasProfilePhoto = false,
    this.profilePhotoUpdatedAt,
  });
  factory OwnerUser.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final fullName = json['full_name'];
    final email = json['email'];
    if (id is! num ||
        fullName is! String ||
        fullName.trim().isEmpty ||
        email is! String ||
        email.trim().isEmpty) {
      throw const FormatException('Invalid owner profile.');
    }
    return OwnerUser(
      id: id.toInt(),
      fullName: fullName.trim(),
      email: email.trim(),
      hasProfilePhoto: json['has_profile_photo'] == true,
      profilePhotoUpdatedAt: json['profile_photo_updated_at'] is String
          ? DateTime.tryParse(json['profile_photo_updated_at'] as String)
          : null,
    );
  }
}
