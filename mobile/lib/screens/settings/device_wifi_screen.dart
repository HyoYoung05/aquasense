import 'dart:async';
import 'package:flutter/material.dart';
import '../../services/device_wifi_service.dart';

class DeviceWifiScreen extends StatefulWidget {
  final DeviceWifiRepository repository;
  const DeviceWifiScreen({
    super.key,
    this.repository = const MobileDeviceWifiService(),
  });
  @override
  State<DeviceWifiScreen> createState() => _DeviceWifiScreenState();
}

class _DeviceWifiScreenState extends State<DeviceWifiScreen> {
  final _form = GlobalKey<FormState>();
  final _ssid = TextEditingController();
  final _password = TextEditingController();
  final _setupCode = TextEditingController();
  List<WifiSetupDevice> _devices = [];
  String? _selected;
  String? _error;
  bool _busy = false;
  bool _scanning = false;
  bool _hidePassword = true;
  bool _openNetwork = false;
  bool _success = false;
  @override
  void dispose() {
    unawaited(widget.repository.cancel());
    _password.clear();
    _setupCode.clear();
    _ssid.dispose();
    _password.dispose();
    _setupCode.dispose();
    super.dispose();
  }

  Future<void> _scan() async {
    setState(() {
      _busy = true;
      _scanning = true;
      _error = null;
      _success = false;
      _selected = null;
      _devices = [];
    });
    try {
      final devices = await widget.repository.scan();
      if (!mounted) return;
      setState(() {
        _devices = devices;
        if (devices.length == 1) _selected = devices.first.id;
        if (devices.isEmpty) {
          _error =
              'No AQUASENSE+ devices found. Hold the ESP32 BOOT button for 3 seconds while it is running, release it, then scan again.';
        }
      });
    } on DeviceWifiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Could not find your device. Check Bluetooth and try again.',
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _scanning = false;
        });
      }
    }
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    final deviceId = _selected;
    if (deviceId == null) {
      setState(() => _error = 'Scan for and select your device first.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _success = false;
    });
    try {
      await widget.repository.provision(
        deviceId: deviceId,
        setupCode: _setupCode.text,
        ssid: _ssid.text,
        password: _openNetwork ? '' : _password.text,
      );
      if (!mounted) return;
      _password.clear();
      _setupCode.clear();
      setState(() => _success = true);
    } on DeviceWifiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(
          () =>
              _error = 'Device setup failed. Check the details and try again.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Device Wi-Fi')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            'Connect your AQUASENSE+ device',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 12),
          const Text(
            'Turn on Bluetooth. Hold the ESP32 BOOT button for 3 seconds while it is running, then release it to start setup. Scan and select your device below.',
          ),
          const SizedBox(height: 8),
          const Text(
            'Use the setup code supplied with your device and a 2.4 GHz Wi-Fi network. Your Wi-Fi password is sent securely over Bluetooth directly to the ESP32.',
          ),
          const SizedBox(height: 20),
          if (!widget.repository.supported)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text(
                  'Device Wi-Fi setup is available in the Android app.',
                ),
              ),
            )
          else ...[
            OutlinedButton.icon(
              onPressed: _busy ? null : _scan,
              icon: const Icon(Icons.bluetooth_searching),
              label: Text(_scanning ? 'Searching…' : 'Find my device'),
            ),
            if (_busy) ...[
              const SizedBox(height: 12),
              const LinearProgressIndicator(),
              const SizedBox(height: 8),
              Text(
                _scanning
                    ? 'Looking for nearby devices…'
                    : 'Connecting securely and checking the Wi-Fi connection. This can take up to 90 seconds.',
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: 16),
              Text(
                _error!,
                key: const Key('device-wifi-error'),
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: 18),
            Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_devices.isNotEmpty) ...[
                    DropdownButtonFormField<String>(
                      key: ValueKey(_devices.map((d) => d.id).join(',')),
                      initialValue: _selected,
                      decoration: const InputDecoration(
                        labelText: 'Your device',
                      ),
                      isExpanded: true,
                      items: _devices
                          .map(
                            (d) => DropdownMenuItem(
                              value: d.id,
                              child: Text(
                                d.name,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: _busy
                          ? null
                          : (value) => setState(() => _selected = value),
                    ),
                    const SizedBox(height: 16),
                  ],
                  TextFormField(
                    key: const Key('device-setup-code'),
                    controller: _setupCode,
                    enabled: !_busy,
                    autocorrect: false,
                    enableSuggestions: false,
                    decoration: const InputDecoration(
                      labelText: 'Device setup code',
                      helperText:
                          '16 characters from the device label or installer',
                    ),
                    validator: setupCodeError,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    key: const Key('device-wifi-name'),
                    controller: _ssid,
                    enabled: !_busy,
                    autocorrect: false,
                    enableSuggestions: false,
                    decoration: const InputDecoration(
                      labelText: 'Wi-Fi name (SSID)',
                    ),
                    validator: wifiNameError,
                  ),
                  const SizedBox(height: 16),
                  if (!_openNetwork)
                    TextFormField(
                      key: const Key('device-wifi-password'),
                      controller: _password,
                      enabled: !_busy,
                      obscureText: _hidePassword,
                      autocorrect: false,
                      enableSuggestions: false,
                      decoration: InputDecoration(
                        labelText: 'Wi-Fi password',
                        suffixIcon: IconButton(
                          onPressed: () =>
                              setState(() => _hidePassword = !_hidePassword),
                          icon: Icon(
                            _hidePassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                          ),
                          tooltip: _hidePassword
                              ? 'Show password'
                              : 'Hide password',
                        ),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Enter the Wi-Fi password or select no password below.'
                          : wifiPasswordError(value),
                    ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _openNetwork,
                    onChanged: _busy
                        ? null
                        : (value) => setState(() {
                            _openNetwork = value ?? false;
                            _password.clear();
                          }),
                    title: const Text('This Wi-Fi network has no password'),
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    key: const Key('save-device-wifi'),
                    onPressed: _busy || _selected == null ? null : _save,
                    icon: const Icon(Icons.wifi),
                    label: const Text('Connect device to Wi-Fi'),
                  ),
                ],
              ),
            ),
            if (_success) ...[
              const SizedBox(height: 16),
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Text(
                    'Wi-Fi connected. Your ESP32 is saving the network and restarting. Return to Monitoring to check for fresh readings.',
                    key: Key('device-wifi-success'),
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    ),
  );
}
