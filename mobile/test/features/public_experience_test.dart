import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:khaledsaad_app/core/api/api_client.dart';
import 'package:khaledsaad_app/core/api/platform_repository.dart';
import 'package:khaledsaad_app/core/theme/app_theme.dart';
import 'package:khaledsaad_app/features/public/public_content_models.dart';
import 'package:khaledsaad_app/features/public/public_content_screen.dart';
import 'package:khaledsaad_app/features/public/public_profile_screen.dart';
import 'package:khaledsaad_app/features/public/public_shell.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test(
    'public content summary parses the API contract and resolves cover URL',
    () {
      final item = PublicContentSummary.fromJson({
        'id': 7,
        'slug': 'marketing-lesson',
        'type': 'lesson',
        'title': 'درس التسويق',
        'excerpt': 'ملخص الدرس',
        'cover_image_url': '/storage/content/lesson.webp',
        'duration_minutes': 15,
        'category': {'name': 'التسويق', 'slug': 'marketing'},
        'locked': false,
      }, siteBaseUrl: 'https://khaledsaad.net');

      expect(item.slug, 'marketing-lesson');
      expect(item.typeLabel, 'درس');
      expect(item.categoryName, 'التسويق');
      expect(
        item.coverImageUrl,
        'https://khaledsaad.net/storage/content/lesson.webp',
      );
    },
  );

  testWidgets('native public navigation exposes five complete destinations', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.build(),
        home: Scaffold(
          bottomNavigationBar: PublicNavigationBar(
            currentIndex: 0,
            onDestinationSelected: (_) {},
          ),
        ),
      ),
    );

    for (final label in [
      'الرئيسية',
      'المعرفة',
      'الأدوات',
      'السيرة',
      'المزيد',
    ]) {
      expect(find.text(label), findsOneWidget);
    }
  });

  test(
    'repository requests the public library and detail by content slug',
    () async {
      final paths = <String>[];
      final client = MockClient((request) async {
        paths.add(request.url.path);
        final body = request.url.path.endsWith('/native-card')
            ? {
                'data': {'id': 3, 'slug': 'native-card', 'title': 'مقال'},
              }
            : {
                'data': <Map<String, dynamic>>[],
                'meta': {'current_page': 1},
              };
        return http.Response(
          jsonEncode(body),
          200,
          headers: {'content-type': 'application/json'},
        );
      });
      final repository = PlatformRepository(ApiClient(client: client));

      await repository.publicContent(page: 1);
      final detail = await repository.publicContentDetail('native-card');

      expect(paths[0], endsWith('/v1/public/content'));
      expect(paths[1], endsWith('/v1/public/content/native-card'));
      expect(detail['slug'], 'native-card');
    },
  );

  testWidgets('professional profile renders biography and full experience', (
    tester,
  ) async {
    final brand = {
      'name': 'خالد سعد',
      'professional_headline': 'مدير التسويق',
      'location': 'عرعر، المملكة العربية السعودية',
      'experience_years': 'أكثر من 10 سنوات',
      'about': ['أمتلك خبرة مهنية موثقة'],
      'experience': [
        {
          'role': 'مدير التسويق',
          'company': 'شركة الشمال التعليمية',
          'period': 'نوفمبر 2024 — حتى الآن',
          'location': 'السعودية',
          'responsibilities': ['أدير الحملات الاستراتيجية'],
        },
      ],
      'education': [
        {
          'degree': 'بكالوريوس تقنية المعلومات',
          'institution': 'جامعة النيلين',
          'period': '2006 — 2010',
        },
      ],
      'credentials': [
        {'name': 'إدارة المشاريع الاحترافية PMP'},
        {
          'name': 'Claude Code in Action',
          'issuer': 'Anthropic',
          'issued': 'أبريل 2026',
          'credential_id': 'dp3a6ruyi8z3',
        },
      ],
      'skills': ['تحليل البيانات'],
      'professional_services': ['استراتيجية المحتوى'],
      'contact': {
        'phone_display': '+966 53 305 2074',
        'whatsapp': 'https://wa.me/966533052074',
        'linkedin': 'https://www.linkedin.com/in/khaledaasaad/',
        'x': 'https://x.com/KhaledAASaad',
      },
    };

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.build(),
        home: Scaffold(
          body: PublicProfileScreen(
            brand: brand,
            profilePdfUrl: 'https://khaledsaad.net/profile.pdf',
          ),
        ),
      ),
    );

    expect(find.text('السيرة المهنية'), findsOneWidget);
    expect(find.text('تنزيل السيرة PDF'), findsOneWidget);
    expect(find.text('شركة الشمال التعليمية'), findsOneWidget);
    expect(find.text('أدير الحملات الاستراتيجية'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('جامعة النيلين'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('جامعة النيلين'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('إدارة المشاريع الاحترافية PMP'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('إدارة المشاريع الاحترافية PMP'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Claude Code in Action'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Anthropic · أبريل 2026'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('LinkedIn'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('LinkedIn'), findsOneWidget);
    expect(find.text('WhatsApp'), findsOneWidget);
  });

  testWidgets('content card renders native cover metadata and title', (
    tester,
  ) async {
    final item = PublicContentSummary.fromJson({
      'id': 3,
      'slug': 'native-card',
      'type': 'article',
      'title': 'مقال داخل التطبيق',
      'excerpt': 'ملخص المقال',
      'duration_minutes': 8,
      'category': {'name': 'المحتوى'},
    });

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.build(),
        home: Scaffold(
          body: PublicContentCard(item: item, onTap: () {}),
        ),
      ),
    );

    expect(find.text('مقال داخل التطبيق'), findsOneWidget);
    expect(find.text('مقال · 8 دقيقة'), findsOneWidget);
    expect(find.text('المحتوى'), findsOneWidget);
  });
}
