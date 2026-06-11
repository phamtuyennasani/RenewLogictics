import 'package:flutter/services.dart';
import 'package:local_auth/local_auth.dart';

class BiometricAvailability {
  const BiometricAvailability({
    required this.canAuthenticate,
    required this.label,
    required this.iconName,
  });

  final bool canAuthenticate;
  final String label;
  final String iconName;
}

class BiometricAuthService {
  BiometricAuthService([LocalAuthentication? auth])
    : _auth = auth ?? LocalAuthentication();

  final LocalAuthentication _auth;

  Future<BiometricAvailability> availability() async {
    try {
      final supported = await _auth.isDeviceSupported();
      final canCheck = await _auth.canCheckBiometrics;
      if (!supported || !canCheck) {
        return const BiometricAvailability(
          canAuthenticate: false,
          label: 'Sinh trắc học',
          iconName: 'fingerprint',
        );
      }

      final biometrics = await _auth.getAvailableBiometrics();
      if (biometrics.isEmpty) {
        return const BiometricAvailability(
          canAuthenticate: false,
          label: 'Sinh trắc học',
          iconName: 'fingerprint',
        );
      }

      if (biometrics.contains(BiometricType.face)) {
        return const BiometricAvailability(
          canAuthenticate: true,
          label: 'Face ID',
          iconName: 'face',
        );
      }
      if (biometrics.contains(BiometricType.fingerprint)) {
        return const BiometricAvailability(
          canAuthenticate: true,
          label: 'Touch ID',
          iconName: 'fingerprint',
        );
      }

      return const BiometricAvailability(
        canAuthenticate: true,
        label: 'Sinh trắc học',
        iconName: 'fingerprint',
      );
    } on PlatformException {
      return const BiometricAvailability(
        canAuthenticate: false,
        label: 'Sinh trắc học',
        iconName: 'fingerprint',
      );
    }
  }

  Future<bool> authenticate({required String reason}) async {
    try {
      return _auth.authenticate(
        localizedReason: reason,
        biometricOnly: true,
        persistAcrossBackgrounding: true,
      );
    } on Object {
      return false;
    }
  }
}
