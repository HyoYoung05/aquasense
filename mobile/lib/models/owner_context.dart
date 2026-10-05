import 'user.dart';

class OwnerEstablishment {
  final int id;
  final String businessName;

  const OwnerEstablishment({required this.id, required this.businessName});

  factory OwnerEstablishment.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final name = json['business_name'];
    if (id is! num || name is! String || name.trim().isEmpty) {
      throw const FormatException('Invalid establishment.');
    }
    return OwnerEstablishment(id: id.toInt(), businessName: name.trim());
  }
}

class OwnerContext {
  final OwnerUser user;
  final List<OwnerEstablishment> establishments;

  const OwnerContext({required this.user, required this.establishments});

  factory OwnerContext.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    final establishments = json['establishments'];
    if (user is! Map<String, dynamic> || establishments is! List) {
      throw const FormatException('Invalid owner context.');
    }
    final parsedEstablishments = <OwnerEstablishment>[];
    for (final item in establishments) {
      if (item is! Map<String, dynamic>) {
        throw const FormatException('Invalid establishment list.');
      }
      parsedEstablishments.add(OwnerEstablishment.fromJson(item));
    }
    return OwnerContext(
      user: OwnerUser.fromJson(user),
      establishments: parsedEstablishments,
    );
  }
}
