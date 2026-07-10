import 'dart:io';

import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

/// يحفظ بايتات إلى ملف مؤقت ثم يفتحه/يشاركه.
class FileExporter {
  const FileExporter._();

  static Future<File> saveBytes(List<int> bytes, String filename) async {
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/$filename');
    await file.writeAsBytes(bytes, flush: true);
    return file;
  }

  /// يحفظ ثم يشارك عبر ورقة المشاركة.
  static Future<void> saveAndShare(List<int> bytes, String filename) async {
    final file = await saveBytes(bytes, filename);
    await SharePlus.instance.share(
      ShareParams(files: [XFile(file.path)]),
    );
  }

  /// يحفظ ثم يفتح بالتطبيق المناسب (مثلاً قارئ PDF).
  static Future<void> saveAndOpen(List<int> bytes, String filename) async {
    final file = await saveBytes(bytes, filename);
    await OpenFilex.open(file.path);
  }
}
