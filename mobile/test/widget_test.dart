import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:studysys_mobile/main.dart';

void main() {
  testWidgets('sign-in screen displays StudySys authentication', (
    tester,
  ) async {
    var themeTogglePressed = false;
    await tester.pumpWidget(
      MaterialApp(
        home: LoginScreen(
          onSignedIn: _ignoreUser,
          isDarkMode: true,
          onToggleTheme: () async => themeTogglePressed = true,
        ),
      ),
    );

    expect(find.text('StudySys'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('New here? Create an account'), findsOneWidget);
    expect(find.byTooltip('Switch to light mode'), findsOneWidget);
    await tester.tap(find.byTooltip('Switch to light mode'));
    await tester.pump();
    expect(themeTogglePressed, isTrue);
  });
}

void _ignoreUser(Map<String, dynamic> user) {}
