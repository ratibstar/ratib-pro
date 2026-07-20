/// SharedPreferences-backed [CacheStore] — presentation cache only.
library;

import 'package:ratib_hr_mobile/core/contracts/cache_store.dart';
import 'package:shared_preferences/shared_preferences.dart';

final class SharedPreferencesCacheStore implements CacheStore {
  SharedPreferencesCacheStore({SharedPreferences? prefs}) : _prefs = prefs;

  SharedPreferences? _prefs;

  Future<SharedPreferences> _ensure() async {
    return _prefs ??= await SharedPreferences.getInstance();
  }

  @override
  Future<void> write(String key, String value) async {
    final p = await _ensure();
    await p.setString(key, value);
  }

  @override
  Future<String?> read(String key) async {
    final p = await _ensure();
    return p.getString(key);
  }

  @override
  Future<void> remove(String key) async {
    final p = await _ensure();
    await p.remove(key);
  }

  @override
  Future<void> clear() async {
    final p = await _ensure();
    await p.clear();
  }
}
