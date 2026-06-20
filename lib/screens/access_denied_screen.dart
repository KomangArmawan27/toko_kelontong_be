import 'package:flutter/material.dart';

class AccessDeniedScreen extends StatelessWidget {
  final String message;

  const AccessDeniedScreen({
    super.key,
    this.message = 'You do not have access to this page.',
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Access Denied')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.lock_outline, size: 64),
              const SizedBox(height: 16),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => Navigator.pushNamedAndRemoveUntil(
                  context,
                  '/',
                  (_) => false,
                ),
                child: const Text('Go to Dashboard'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
