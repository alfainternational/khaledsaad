import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/config/app_environment.dart';

void main() {
  test('uses the Android emulator local API URL by default', () {
    expect(AppEnvironment.apiBaseUrl, 'http://10.0.2.2/khaledsaad/public/api');
    expect(AppEnvironment.appBuild, 16);
  });
}
