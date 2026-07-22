/// Documents repository — presentation orchestration over ports only.
library;

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:ratib_hr_mobile/core/contracts/documents_port.dart';
import 'package:ratib_hr_mobile/core/errors/app_failure.dart';
import 'package:ratib_hr_mobile/core/offline/ess_read_cache.dart';

final class DocumentsRepository {
  DocumentsRepository({
    required DocumentsPort documents,
    CacheStore? cache,
  })  : _documents = documents,
        _cache = cache;

  final DocumentsPort _documents;
  final CacheStore? _cache;

  Future<EssCachedList> loadList({String? category}) {
    return EssReadCache.fetchList(
      key: EssReadCache.documentsList,
      cache: _cache,
      fetch: () => _documents.listMine(category: null),
    );
  }

  Future<EssCachedMap> loadDetail(String id) async {
    try {
      final row = await _documents.detail(id);
      return EssCachedMap(data: row);
    } on AppFailure catch (e) {
      if (EssReadCache.isConnectivity(e)) {
        EssReadCache.markOffline(e.message);
        final cached = await EssReadCache.readList(
          EssReadCache.documentsList,
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

  Future<({List<int> bytes, String? contentType, String? filename})?> download(
    String id,
  ) =>
      _documents.download(id);
}
