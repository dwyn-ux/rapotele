import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';

class WebAppScreen extends StatefulWidget {
  const WebAppScreen({super.key});

  @override
  State<WebAppScreen> createState() => _WebAppScreenState();
}

class _WebAppScreenState extends State<WebAppScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  double _progress = 0.0;
  String _currentTitle = 'E-Raport';

  @override
  void initState() {
    super.initState();
    _requestLocationPermission();
    _initWebView();
    _listenToNotifications();
  }

  Future<void> _requestLocationPermission() async {
    try {
      final status = await Permission.location.request();
      debugPrint('Location permission status: $status');
    } catch (e) {
      debugPrint('Location permission request error: $e');
    }
  }

  void _initWebView() {
    final startUrl = _resolveInitialUrl();

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFF8FAFC))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (int progress) {
            setState(() {
              _progress = progress / 100.0;
            });
          },
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
            });
          },
          onPageFinished: (String url) async {
            final title = await _controller.getTitle();
            setState(() {
              _isLoading = false;
              if (title != null && title.isNotEmpty) {
                _currentTitle = title;
              }
            });
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint('WebView Error: ${error.errorCode} - ${error.description}');
          },
        ),
      );

    if (_controller.platform is AndroidWebViewController) {
      final androidController = _controller.platform as AndroidWebViewController;
      androidController.setGeolocationPermissionsPromptCallbacks(
        onShowPrompt: (GeolocationPermissionsRequestParams params) async {
          final status = await Permission.location.request();
          return GeolocationPermissionsResponse(
            allow: status.isGranted,
            retain: true,
          );
        },
      );
    }

    _controller.loadRequest(Uri.parse(startUrl));
  }

  String _resolveInitialUrl() {
    var base = ApiService().baseUrl;
    if (base.isEmpty || base.contains('localhost')) {
      base = 'http://10.49.62.121:8000';
    }
    return base;
  }

  void _listenToNotifications() {
    NotificationService.onNotificationClick.stream.listen((data) {
      final targetPage = data['page'] ?? data['url'];
      if (targetPage != null && targetPage.toString().isNotEmpty) {
        _navigateToPage(targetPage.toString());
      }
    });

    // Cek juga jika app dibuka dari notifikasi saat ditutup
    NotificationService().getInitialNotificationPayload().then((data) {
      if (data != null) {
        final targetPage = data['page'] ?? data['url'];
        if (targetPage != null && targetPage.toString().isNotEmpty) {
          _navigateToPage(targetPage.toString());
        }
      }
    });
  }

  void _navigateToPage(String pageOrUrl) {
    String fullUrl;
    if (pageOrUrl.startsWith('http://') || pageOrUrl.startsWith('https://')) {
      fullUrl = pageOrUrl;
    } else {
      final base = ApiService().baseUrl;
      final cleanPath = pageOrUrl.startsWith('/') ? pageOrUrl : '/$pageOrUrl';
      fullUrl = '$base$cleanPath';
    }
    _controller.loadRequest(Uri.parse(fullUrl));
  }

  Future<void> _showServerSettings() async {
    final textController = TextEditingController(text: ApiService().baseUrl);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.dns, color: Color(0xFF2563EB)),
            SizedBox(width: 8),
            Text('Pengaturan Server Web', style: TextStyle(fontSize: 18)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Masukkan alamat server E-Raport:',
              style: TextStyle(fontSize: 13, color: Colors.black54),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: textController,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                hintText: 'http://10.49.62.121:8000',
                labelText: 'URL Server',
                isDense: true,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                _buildPresetChip('http://localhost:8000', textController),
                _buildPresetChip('http://10.49.62.121:8000', textController),
                _buildPresetChip('https://raport.smpmuashidiq.sch.id', textController),
              ],
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF2563EB),
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Simpan & Buka'),
          ),
        ],
      ),
    );

    if (confirmed == true && textController.text.trim().isNotEmpty) {
      await ApiService().setBaseUrl(textController.text.trim());
      _controller.loadRequest(Uri.parse(ApiService().baseUrl));
    }
  }

  Widget _buildPresetChip(String url, TextEditingController controller) {
    return ActionChip(
      label: Text(
        url.replaceAll('http://', '').replaceAll('https://', ''),
        style: const TextStyle(fontSize: 11),
      ),
      onPressed: () {
        controller.text = url;
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        if (await _controller.canGoBack()) {
          await _controller.goBack();
        } else {
          if (context.mounted) {
            final shouldExit = await showDialog<bool>(
              context: context,
              builder: (ctx) => AlertDialog(
                title: const Text('Keluar Aplikasi?'),
                content: const Text('Apakah Anda yakin ingin keluar dari E-Raport?'),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(ctx, false),
                    child: const Text('Tidak'),
                  ),
                  ElevatedButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    child: const Text('Keluar'),
                  ),
                ],
              ),
            );
            if (shouldExit == true && context.mounted) {
              Navigator.of(context).pop();
            }
          }
        }
      },
      child: Scaffold(
        appBar: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: AppBar(
            elevation: 0,
            backgroundColor: const Color(0xFF2563EB),
            foregroundColor: Colors.white,
            title: Text(
              _currentTitle.replaceAll(' — E-Raport KumerBot', ''),
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
              overflow: TextOverflow.ellipsis,
            ),
            actions: [
              IconButton(
                icon: const Icon(Icons.refresh, size: 20),
                tooltip: 'Muat ulang',
                onPressed: () => _controller.reload(),
              ),
              IconButton(
                icon: const Icon(Icons.settings, size: 20),
                tooltip: 'Ganti Server',
                onPressed: _showServerSettings,
              ),
            ],
            bottom: _isLoading
                ? PreferredSize(
                    preferredSize: const Size.fromHeight(2),
                    child: LinearProgressIndicator(
                      value: _progress > 0 ? _progress : null,
                      backgroundColor: Colors.transparent,
                      valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : null,
          ),
        ),
        body: SafeArea(
          bottom: true,
          top: false,
          child: WebViewWidget(controller: _controller),
        ),
      ),
    );
  }
}
