/// Payslips repository — presentation orchestration over ports only.
library;

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/payslip_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';

final class PayslipRepository {
  PayslipRepository({
    required PayslipPort payslips,
    CacheStore? cache,
  })  : _payslips = payslips,
        _cache = cache;

  final PayslipPort _payslips;
  final CacheStore? _cache;

  Future<EssCachedList> loadList() {
    return EssReadCache.fetchList(
      key: EssReadCache.payslipsList,
      cache: _cache,
      fetch: () => _payslips.listMine(),
    );
  }

  Future<EssCachedMap> loadDetail(String id) async {
    try {
      final row = await _payslips.detail(id);
      return EssCachedMap(data: row);
    } on AppFailure catch (e) {
      if (EssReadCache.isConnectivity(e)) {
        EssReadCache.markOffline(e.message);
        final cached = await EssReadCache.readList(
          EssReadCache.payslipsList,
          cache: _cache,
        );
        final hit = cached == null ? null : EssReadCache.findById(cached, id);
        return EssCachedMap(
          data: hit ?? const {},
          fromCache: hit != null,
          offlineDegraded: true,
        );
      }
      rethrow;
    }
  }

  Future<({List<int> bytes, String? contentType, String? filename})> download(
    String id,
  ) =>
      _payslips.download(id);
}
