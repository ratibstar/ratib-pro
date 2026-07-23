/// Product brand display — رتب / RATEB.
///
/// Does **not** rewrite salary wording (الراتب، كشف راتب، بدون راتب، …).
library;

abstract final class BrandDisplay {
  static const String arabicName = 'رتب';
  static const String englishName = 'RATEB';
  static const String arabicHrTitle = 'رتب — الموارد البشرية';
  static const String englishHrTitle = 'RATEB HR';

  static String normalizeAppName(String raw) {
    var s = raw.trim();
    if (s.isEmpty) return s;

    const exact = <String, String>{
      'راتب': arabicName,
      'راتب — الموارد البشرية': arabicHrTitle,
      'راتب - الموارد البشرية': arabicHrTitle,
      'راتب – الموارد البشرية': arabicHrTitle,
      'RATIB': englishName,
      'Ratib': englishName,
      'ratib': englishName,
      'RATIB HR': englishHrTitle,
      'Ratib HR': englishHrTitle,
      'RATIB - Human Resources': 'RATEB - Human Resources',
      'Ratib - Human Resources': 'RATEB - Human Resources',
    };
    final hit = exact[s];
    if (hit != null) return hit;

    const pairs = <String, String>{
      'راتب — الموارد البشرية': arabicHrTitle,
      'راتب - الموارد البشرية': arabicHrTitle,
      'راتب – الموارد البشرية': arabicHrTitle,
      'راتب ERP': 'رتب ERP',
      'نظام راتب ERP': 'نظام رتب ERP',
      'نظام راتب': 'نظام رتب',
      'بحساب راتب': 'بحساب رتب',
      'RATIB ERP': 'RATEB ERP',
      'Ratib ERP': 'RATEB ERP',
      'RATIB HR': englishHrTitle,
      'Ratib HR': englishHrTitle,
      'RATIB': englishName,
      'Ratib': englishName,
    };
    for (final e in pairs.entries) {
      if (s.contains(e.key)) {
        s = s.replaceAll(e.key, e.value);
      }
    }
    return s;
  }
}
