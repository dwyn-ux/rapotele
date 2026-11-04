import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:erapot_mobile/main.dart';
import 'package:erapot_mobile/services/api_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('App renders LoginScreen when not authenticated', (WidgetTester tester) async {
    SharedPreferences.setMockInitialValues({});
    await ApiService().init();

    await tester.pumpWidget(const ERaporMobileApp());
    await tester.pumpAndSettle();

    expect(find.text('E-Rapor Kurikulum Merdeka'), findsOneWidget);
    expect(find.text('Masuk Aplikasi'), findsOneWidget);
  });
}
