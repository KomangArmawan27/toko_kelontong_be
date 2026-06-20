class AppUser {
  static const String shopOwner = 'shop_owner';
  static const String shopKeeper = 'shop_keeper';
  static const String customer = 'customer';

  final int? id;
  final String name;
  final String email;
  final String role;

  const AppUser({
    this.id,
    required this.name,
    required this.email,
    required this.role,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: _asInt(json['id']),
      name: (json['name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      role: (json['role'] ?? customer).toString(),
    );
  }

  bool get isShopOwner => role == shopOwner;

  bool get isShopKeeper => role == shopKeeper;

  bool get isCustomer => role == customer;

  bool hasRole(String value) => role == value;

  String get label {
    switch (role) {
      case shopOwner:
        return 'Shop Owner';
      case shopKeeper:
        return 'Shop Keeper';
      default:
        return 'Customer';
    }
  }
}

int? _asInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}
