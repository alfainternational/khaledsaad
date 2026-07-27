import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/features/consultations/models.dart';

void main() {
  test(
    'consultation evidence exposes the server extraction state and hash',
    () {
      final evidence = ConsultationEvidence.fromJson(const {
        'id': 1,
        'name': 'brief.txt',
        'type': 'uploaded_file',
        'confidence': 'high',
        'size': 120,
        'mime_type': 'text/plain',
        'extraction_status': 'completed',
        'sha256': 'abc123',
        'review_required': false,
      });

      expect(evidence.extractionStatus, 'completed');
      expect(evidence.extractionLabel, 'تم استخراج المحتوى');
      expect(evidence.sha256, 'abc123');
      expect(evidence.mimeType, 'text/plain');
    },
  );
}
