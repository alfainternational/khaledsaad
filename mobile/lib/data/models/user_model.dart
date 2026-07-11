/// نموذج المستخدم — يعكس UserResource من الـ API.
class UserModel {
  const UserModel({
    required this.publicId,
    required this.name,
    required this.email,
    this.locale,
  });

  final String publicId;
  final String name;
  final String email;
  final String? locale;

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      publicId: json['public_id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      locale: json['locale']?.toString(),
    );
  }
}
