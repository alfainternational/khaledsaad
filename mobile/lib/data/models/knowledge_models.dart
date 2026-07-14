/// مستند معرفة مرفوع — يعكس KnowledgeUploadResource.
class KnowledgeUpload {
  const KnowledgeUpload({
    required this.publicId,
    required this.originalName,
    required this.status,
    this.mimeType,
    this.extension,
    this.errorCode,
    this.byteSize = 0,
    this.createdAt,
  });

  final String publicId;
  final String originalName;
  final String status;
  final String? mimeType;
  final String? extension;
  final String? errorCode;
  final int byteSize;
  final String? createdAt;

  bool get isFailed => status == 'failed' || status == 'error';
  bool get isReady =>
      status == 'completed' ||
      status == 'ready' ||
      status == 'extracted' ||
      status == 'processed';
  bool get isProcessing => !isFailed && !isReady;

  String get statusLabel {
    if (isFailed) return 'فشل';
    if (isReady) return 'جاهز';
    return 'قيد المعالجة';
  }

  String get sizeLabel {
    if (byteSize <= 0) return '';
    if (byteSize < 1024) return '$byteSize B';
    if (byteSize < 1024 * 1024) return '${(byteSize / 1024).toStringAsFixed(0)} KB';
    return '${(byteSize / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  factory KnowledgeUpload.fromJson(Map<String, dynamic> json) => KnowledgeUpload(
        publicId: json['public_id']?.toString() ?? '',
        originalName: json['original_name']?.toString() ?? 'ملف',
        status: json['status']?.toString() ?? 'pending',
        mimeType: json['mime_type']?.toString(),
        extension: json['extension']?.toString(),
        errorCode: json['error_code']?.toString(),
        byteSize:
            (json['byte_size'] is num) ? (json['byte_size'] as num).toInt() : 0,
        createdAt: json['created_at']?.toString(),
      );
}
