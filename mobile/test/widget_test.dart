import 'package:flutter_test/flutter_test.dart';
import 'package:pinnacle_school/core/api.dart';
import 'package:pinnacle_school/core/widgets.dart';

void main() {
  test('the school address is cleaned up whatever way the user types it', () {
    expect(ApiClient.normalizeBaseUrl('myschool.edu.pk'), 'https://myschool.edu.pk');
    expect(ApiClient.normalizeBaseUrl(' https://myschool.edu.pk/ '), 'https://myschool.edu.pk');
    expect(ApiClient.normalizeBaseUrl('http://192.168.1.5:8000/api'), 'http://192.168.1.5:8000');
    expect(ApiClient.normalizeBaseUrl(''), '');
  });

  test('api paths go under /api and signed file links are used as they are', () {
    final api = ApiClient(baseUrl: 'https://school.test');
    expect(api.uri('erp/notifications', {'page': 2, 'unread': null}).toString(), 'https://school.test/api/erp/notifications?page=2');
    expect(api.uri('https://school.test/files/9?signature=abc').toString(), 'https://school.test/files/9?signature=abc');
  });

  test('money arrives in paisa and is shown in rupees', () {
    expect(money(1250000), 'Rs 12,500');
    expect(money(null), 'Rs 0');
  });

  test('lists read both plain arrays and paginated {items, meta}', () {
    expect(asList([{'id': 1}]).length, 1);
    expect(asList({'items': [{'id': 1}, {'id': 2}], 'meta': {}}).length, 2);
    expect(asList(null), isEmpty);
  });
}
