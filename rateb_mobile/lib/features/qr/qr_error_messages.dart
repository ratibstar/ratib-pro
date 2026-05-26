import '../../core/api/api_exception.dart';

/// Enterprise-facing QR login error copy.
String friendlyQrErrorMessage(Object error) {
  if (error is ApiException) {
    return _fromApi(error);
  }
  return 'Unable to verify your workforce identity. Please try again.';
}

String _fromApi(ApiException e) {
  switch (e.code) {
    case 'nonce_reused':
      return 'This identity badge has already been used. Ask your administrator for a new badge QR.';
    case 'invalid':
      return 'Unrecognized workforce badge. Align the QR inside the frame or use password sign-in.';
    case 'config_error':
      return 'Workforce identity service is temporarily unavailable. Try again shortly or use password sign-in.';
    case 'invalid_credentials':
      return 'This badge is not authorized for mobile access.';
  }

  if (e.statusCode == 401) {
    return 'Workforce badge could not be verified. It may be expired or invalid.';
  }
  if (e.statusCode == 0) {
    return 'No network connection. Check your internet and try again.';
  }
  if (e.statusCode == 408) {
    return 'Connection timed out. Check your network and try again.';
  }

  final msg = e.message.trim();
  if (msg.isNotEmpty &&
      !msg.toLowerCase().contains('exception') &&
      msg.length < 120) {
    return msg;
  }
  return 'Unable to verify your workforce identity. Please try again.';
}
