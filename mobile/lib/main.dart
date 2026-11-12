import 'package:flutter/material.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'services/api_service.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'services/notification_service.dart';
import 'services/fcm_service.dart';
import 'screens/web_app_screen.dart';

/// Handler pesan FCM saat app di background/tertutup. Harus top-level.
/// Pesan dengan blok `notification` otomatis ditampilkan Android di status bar.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Indonesian locale formatting
  await initializeDateFormatting('id_ID', null);

  // Initialize persistent API & Notification services
  await ApiService().init();
  await NotificationService().init();

  // Firebase Cloud Messaging (push saat app tertutup)
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
  await FcmService().init();

  runApp(const ERaporMobileApp());
}

class ERaporMobileApp extends StatelessWidget {
  const ERaporMobileApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'E-Raport KumerBot',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF2563EB),
          primary: const Color(0xFF2563EB),
          secondary: const Color(0xFF1D4ED8),
          surface: Colors.white,
        ),
        scaffoldBackgroundColor: const Color(0xFFF8FAFC),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF2563EB),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      home: const WebAppScreen(),
    );
  }
}
