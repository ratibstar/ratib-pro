import 'package:flutter_test/flutter_test.dart';
import 'package:ratib_hr_mobile/core/brand/brand_display.dart';

void main() {
  test('normalizes product brand without touching salary phrases', () {
    expect(BrandDisplay.normalizeAppName('راتب — الموارد البشرية'), 'رتب — الموارد البشرية');
    expect(BrandDisplay.normalizeAppName('راتب - الموارد البشرية'), 'رتب — الموارد البشرية');
    expect(BrandDisplay.normalizeAppName('راتب'), 'رتب');
    expect(BrandDisplay.normalizeAppName('RATIB HR'), 'RATEB HR');
    expect(BrandDisplay.normalizeAppName('Ratib ERP'), 'RATEB ERP');
    expect(BrandDisplay.normalizeAppName('كشف راتب'), 'كشف راتب');
    expect(BrandDisplay.normalizeAppName('الراتب الأساسي'), 'الراتب الأساسي');
  });
}
