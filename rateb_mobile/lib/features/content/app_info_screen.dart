import 'package:flutter/material.dart';

import '../../core/services/app_content_service.dart';
import '../../l10n/app_localizations.dart';
import '../../shared/widgets/empty_state.dart';

/// Offers + app information pages managed from ERP Admin → Mobile Apps.
class AppInfoScreen extends StatefulWidget {
  const AppInfoScreen({super.key});

  @override
  State<AppInfoScreen> createState() => _AppInfoScreenState();
}

class _AppInfoScreenState extends State<AppInfoScreen> {
  late Future<AppContentBundle> _future;

  static const _pageTitlesEn = {
    'about': 'About',
    'privacy': 'Privacy policy',
    'terms': 'Terms of service',
    'faq': 'FAQ',
    'help': 'Help',
    'home': 'Welcome',
  };
  static const _pageTitlesAr = {
    'about': 'عن التطبيق',
    'privacy': 'سياسة الخصوصية',
    'terms': 'الشروط والأحكام',
    'faq': 'الأسئلة الشائعة',
    'help': 'المساعدة',
    'home': 'مرحباً',
  };

  bool get _arabic => AppLocalizations.of(context).isArabic;

  @override
  void initState() {
    super.initState();
    _future = AppContentService.instance.load();
  }

  Future<void> _refresh() async {
    final next = AppContentService.instance.load(refresh: true);
    setState(() => _future = next);
    await next;
  }

  Widget _localizedText(String text, {TextStyle? style}) {
    final rtl = RegExp(r'[\u0600-\u06FF]').hasMatch(text);
    return Text(
      text,
      style: style,
      textDirection: rtl ? TextDirection.rtl : TextDirection.ltr,
      textAlign: rtl ? TextAlign.right : TextAlign.left,
    );
  }

  @override
  Widget build(BuildContext context) {
    final arabic = _arabic;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(arabic ? 'العروض والمعلومات' : 'Offers & info')),
      body: FutureBuilder<AppContentBundle>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                children: [
                  const SizedBox(height: 120),
                  EmptyState(
                    icon: Icons.wifi_off_rounded,
                    title: arabic ? 'تعذّر التحميل' : 'Could not load',
                    message: arabic ? 'اسحب للتحديث.' : 'Pull down to try again.',
                  ),
                ],
              ),
            );
          }
          final bundle = snap.data ?? AppContentBundle.empty;
          if (bundle.isEmpty) {
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                children: [
                  const SizedBox(height: 120),
                  EmptyState(
                    icon: Icons.local_offer_outlined,
                    title: arabic ? 'لا يوجد محتوى بعد' : 'Nothing here yet',
                    message: arabic
                        ? 'ستظهر هنا العروض ومعلومات التطبيق.'
                        : 'Offers and app information will appear here.',
                  ),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (bundle.offers.isNotEmpty) ...[
                  Text(arabic ? 'العروض' : 'Offers', style: theme.textTheme.titleMedium),
                  const SizedBox(height: 8),
                  for (final o in bundle.offers) _offerCard(o, arabic, theme),
                  const SizedBox(height: 16),
                ],
                if (bundle.pages.isNotEmpty) ...[
                  Text(arabic ? 'معلومات التطبيق' : 'App information',
                      style: theme.textTheme.titleMedium),
                  const SizedBox(height: 8),
                  for (final p in bundle.pages)
                    Card(
                      child: ExpansionTile(
                        title: _localizedText(
                          pickLocalized(p.titleAr, p.titleEn, arabic: arabic).isNotEmpty
                              ? pickLocalized(p.titleAr, p.titleEn, arabic: arabic)
                              : ((arabic ? _pageTitlesAr : _pageTitlesEn)[p.slug] ?? p.slug),
                        ),
                        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        children: [
                          _localizedText(pickLocalized(p.bodyAr, p.bodyEn, arabic: arabic)),
                        ],
                      ),
                    ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _offerCard(AppOffer o, bool arabic, ThemeData theme) {
    final title = pickLocalized(o.titleAr, o.titleEn, arabic: arabic);
    final body = pickLocalized(o.bodyAr, o.bodyEn, arabic: arabic);
    return Card(
      clipBehavior: Clip.antiAlias,
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (o.imageUrl.isNotEmpty)
            Image.network(
              o.imageUrl,
              height: 150,
              fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const SizedBox.shrink(),
            ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _localizedText(
                        title,
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                      ),
                    ),
                    if (o.discountLabel.isNotEmpty)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: theme.colorScheme.error,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          o.discountLabel,
                          style: theme.textTheme.labelSmall?.copyWith(
                            color: theme.colorScheme.onError,
                          ),
                        ),
                      ),
                  ],
                ),
                if (body.isNotEmpty) ...[
                  const SizedBox(height: 6),
                  _localizedText(body, style: theme.textTheme.bodySmall),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
