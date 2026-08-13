import 'package:flutter/widgets.dart';

class AppStrings {
  AppStrings(this.locale);

  final Locale locale;

  static AppStrings of(BuildContext context) =>
      AppStrings(Localizations.localeOf(context));

  static const _values = <String, Map<String, String>>{
    'experience_question': {
      'ar': 'ماذا تريد أن تفعل الآن؟',
      'en': 'What do you want to do now?',
      'fr': 'Que souhaitez-vous faire maintenant ?',
    },
    'business_choice': {
      'ar': 'أريد تحسين تسويق مشروعي',
      'en': 'I want to improve my project marketing',
      'fr': 'Je veux améliorer le marketing de mon projet',
    },
    'business_description': {
      'ar': 'أضف مشروعك، شخّص وضعه، واحصل على أولويات ومهام تتابعها.',
      'en':
          'Add your project, diagnose its situation, and follow clear priorities and tasks.',
      'fr':
          'Ajoutez votre projet, diagnostiquez sa situation et suivez des priorités et des tâches claires.',
    },
    'learning_choice': {
      'ar': 'أريد تعلّم التسويق بالتطبيق',
      'en': 'I want to learn marketing by doing',
      'fr': 'Je veux apprendre le marketing par la pratique',
    },
    'learning_description': {
      'ar':
          'اتبع دروسًا عملية، طبّق ما تتعلمه، واحصل على تقييم يساعدك على التحسن.',
      'en':
          'Follow practical lessons, apply what you learn, and get feedback that helps you improve.',
      'fr':
          'Suivez des leçons pratiques, appliquez vos acquis et recevez une évaluation pour progresser.',
    },
    'other_later': {
      'ar': 'يمكنك تفعيل المسار الآخر لاحقًا دون إنشاء حساب جديد.',
      'en':
          'You can activate the other path later without creating another account.',
      'fr':
          'Vous pourrez activer l’autre parcours plus tard sans créer un nouveau compte.',
    },
    'next_task': {
      'ar': 'مهمتك التالية',
      'en': 'Your next task',
      'fr': 'Votre prochaine tâche',
    },
    'my_path': {'ar': 'مساري', 'en': 'My path', 'fr': 'Mon parcours'},
    'change_path': {
      'ar': 'تغيير ما أعمل عليه الآن',
      'en': 'Change what I am working on',
      'fr': 'Changer mon activité actuelle',
    },
    'activate': {'ar': 'تفعيل', 'en': 'Activate', 'fr': 'Activer'},
    'switch': {'ar': 'انتقل', 'en': 'Switch', 'fr': 'Changer'},
    'logout': {'ar': 'خروج', 'en': 'Log out', 'fr': 'Se déconnecter'},
    'duration_result': {
      'ar': '{minutes} دقيقة · {result}',
      'en': '{minutes} min · {result}',
      'fr': '{minutes} min · {result}',
    },
    'start_application': {
      'ar': 'ابدأ التطبيق',
      'en': 'Start application',
      'fr': 'Commencer l’application',
    },
    'question_progress': {
      'ar': 'السؤال {current} من {total}',
      'en': 'Question {current} of {total}',
      'fr': 'Question {current} sur {total}',
    },
    'save_continue': {
      'ar': 'احفظ وانتقل للتالي',
      'en': 'Save and continue',
      'fr': 'Enregistrer et continuer',
    },
    'save_answer': {
      'ar': 'احفظ الإجابة',
      'en': 'Save answer',
      'fr': 'Enregistrer la réponse',
    },
    'submit_review': {
      'ar': 'أرسل للمراجعة',
      'en': 'Submit for review',
      'fr': 'Envoyer pour évaluation',
    },
    'review_in_progress': {
      'ar': 'أرسلنا تطبيقك للمراجعة. ستظهر النتيجة في مسار التعلم.',
      'en':
          'Your application is under review. Its result will appear in your learning path.',
      'fr':
          'Votre application est en cours d’évaluation. Le résultat apparaîtra dans votre parcours.',
    },
    'expected_result': {
      'ar': 'ما الذي ستحصل عليه',
      'en': 'What you will get',
      'fr': 'Ce que vous obtiendrez',
    },
    'answer_example': {
      'ar': 'مثال: {example}',
      'en': 'Example: {example}',
      'fr': 'Exemple : {example}',
    },
    'answer_required': {
      'ar': 'أضف إجابة عملية قبل المتابعة.',
      'en': 'Add a practical answer before continuing.',
      'fr': 'Ajoutez une réponse concrète avant de continuer.',
    },
    'previous_question': {
      'ar': 'السؤال السابق',
      'en': 'Previous question',
      'fr': 'Question précédente',
    },
    'back': {'ar': 'عودة', 'en': 'Back', 'fr': 'Retour'},
    'register_title': {
      'ar': 'أنشئ حسابك واختر هدفك',
      'en': 'Create your account and choose your goal',
      'fr': 'Créez votre compte et choisissez votre objectif',
    },
    'login_title': {
      'ar': 'أهلًا بعودتك',
      'en': 'Welcome back',
      'fr': 'Heureux de vous revoir',
    },
    'register_lead': {
      'ar': 'حساب واحد يحفظ تقدم تعلمك وبيانات مشروعك.',
      'en': 'One account keeps your learning progress and project data.',
      'fr':
          'Un seul compte conserve votre progression et les données de votre projet.',
    },
    'login_lead': {
      'ar': 'سجّل الدخول للمتابعة من حيث توقفت.',
      'en': 'Sign in to continue where you left off.',
      'fr': 'Connectez-vous pour reprendre là où vous vous êtes arrêté.',
    },
    'name': {'ar': 'الاسم', 'en': 'Name', 'fr': 'Nom'},
    'name_required': {
      'ar': 'الاسم مطلوب.',
      'en': 'Name is required.',
      'fr': 'Le nom est obligatoire.',
    },
    'email': {'ar': 'البريد الإلكتروني', 'en': 'Email', 'fr': 'Adresse e-mail'},
    'email_invalid': {
      'ar': 'أدخل بريدًا صحيحًا.',
      'en': 'Enter a valid email.',
      'fr': 'Saisissez une adresse e-mail valide.',
    },
    'password': {'ar': 'كلمة المرور', 'en': 'Password', 'fr': 'Mot de passe'},
    'password_help': {
      'ar': 'ثمانية أحرف على الأقل.',
      'en': 'At least eight characters.',
      'fr': 'Au moins huit caractères.',
    },
    'register_submit': {
      'ar': 'أنشئ حسابك وتابع',
      'en': 'Create account and continue',
      'fr': 'Créer le compte et continuer',
    },
    'login_submit': {
      'ar': 'سجّل الدخول',
      'en': 'Sign in',
      'fr': 'Se connecter',
    },
    'forgot_password': {
      'ar': 'نسيت كلمة المرور؟',
      'en': 'Forgot your password?',
      'fr': 'Mot de passe oublié ?',
    },
    'have_account': {
      'ar': 'لديك حساب؟ سجّل الدخول',
      'en': 'Already have an account? Sign in',
      'fr': 'Vous avez déjà un compte ? Connectez-vous',
    },
    'need_account': {
      'ar': 'ليس لديك حساب؟ أنشئ حسابًا',
      'en': 'Need an account? Create one',
      'fr': 'Besoin d’un compte ? Créez-en un',
    },
  };

  String text(String key, {Map<String, String> values = const {}}) {
    final translations = _values[key];
    var text = translations?[locale.languageCode] ?? translations?['ar'] ?? key;
    for (final entry in values.entries) {
      text = text.replaceAll('{${entry.key}}', entry.value);
    }
    return text;
  }
}
