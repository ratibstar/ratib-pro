/// Payment methods architecture — ERP `GET /api/v1/hr/payment-methods`.
library;

abstract interface class PaymentMethodsPort {
  Future<Map<String, Object?>> list();
}
