import 'package:flutter_test/flutter_test.dart';

import 'package:ksgrowth_mobile/core/error/api_exception.dart';

void main() {
  test('ApiException.fromJson يقرأ العقد الموحّد للأخطاء', () {
    final ex = ApiException.fromJson(
      {
        'message': 'بيانات غير صحيحة',
        'code': 'VALIDATION_ERROR',
        'errors': {
          'email': ['البريد مطلوب'],
        },
      },
      status: 422,
    );

    expect(ex.isValidation, isTrue);
    expect(ex.code, 'VALIDATION_ERROR');
    expect(ex.fieldError('email'), 'البريد مطلوب');
  });

  test('ApiException.network يعطي رمز NETWORK_ERROR', () {
    final ex = ApiException.network();
    expect(ex.isNetwork, isTrue);
  });
}
