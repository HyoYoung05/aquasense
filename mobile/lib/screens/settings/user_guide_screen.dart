import 'package:flutter/material.dart';
import '../../theme/app_theme.dart';

class UserGuideScreen extends StatelessWidget {
  const UserGuideScreen({super.key});

  static const walkthrough = [
    'Sign in with your email or phone number and password.',
    'Open Home to see your shop, grease trap, and latest update.',
    'Open Monitoring to check your readings. Swipe down to refresh.',
    'Open Alerts to read any warning. Ask the Barangay for help if needed.',
    'Open Oil Surrender, tap New Surrender, fill in the details, add a clear photo, and send.',
    'Open Incentives. Choose Rice Incentives or Rice History to check your rice reward.',
    'Open Settings to change your email or password and allow alerts on your phone.',
    'Use Sign out when you finish on a phone shared with others.',
  ];

  static const manual = <String, List<String>>{
    'Your account': [
      'Ask the Barangay to create your account and link it to your shop.',
      'Use your own email or phone number to sign in. Keep your password private.',
      'If the form says too many tries, wait for the time shown. Ask the Barangay for help if you forgot your password.',
    ],
    'Home and Monitoring': [
      'You can only see the grease trap linked to your account.',
      'Check the time of the last reading before using the numbers.',
      'Online means the device is assigned and active in the system. It does not prove the device has power right now.',
      'No data or Not connected means no usable reading is ready. It does not mean the value is zero.',
      'Under the chart, choose a time and a reading. Tap a point to see its value.',
      'If readings stop changing, check power and Wi-Fi. Ask the Barangay for help. Do not touch wet wires.',
    ],
    'Alerts': [
      'Open Alerts and tap a warning to read more.',
      'Use Status, Severity, and Date Range to choose which warnings to see.',
      'Tell the Barangay when the trap needs help. Barangay staff update the warning after they check it.',
      'Swipe down to get the latest list. A new warning may appear if the problem happens again.',
    ],
    'Oil Surrender': [
      'Tap New Surrender. Choose your trap and enter the amount and unit shown on the form.',
      'Add a clear photo of the oil you are handing over. Check your details before you send.',
      'Pending means the Barangay has not checked it yet. Under Review means they are checking it.',
      'Approved means it was accepted. Rejected means it was not accepted; open the record to read the note.',
    ],
    'Rice Incentives and Rice History': [
      'Open the menu and tap Incentives to show its two choices.',
      'Rice Incentives shows your rice reward. Rice History shows past records.',
      'The Barangay must approve the oil and work out the reward first. Sending a photo does not give a reward right away.',
      'Pending Distribution means rice has not been handed over yet. Distributed means staff marked it as handed over.',
      'Ask the Barangay if a reward or amount looks wrong.',
    ],
    'Profile and Settings': [
      'Open Profile, then Add photo or Change photo to choose your picture.',
      'In Settings, use Change email or Change password. Enter your current password when asked.',
      'After changing your password, sign in again with the new one.',
      'Under App permissions, allow Notifications to let the phone show alerts when available.',
      'Display over other apps is optional. Your phone may still limit pop-ups. These options are for the Android app.',
    ],
    'When something goes wrong': [
      'Check your internet, then swipe down to refresh.',
      'Check that your shop and trap names are right. Tell the Barangay if they are wrong.',
      'If a photo will not send, try a smaller, clear photo and check your internet.',
      'Tell the Barangay which page failed and what the message says. Never send your password.',
    ],
  };

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Help and guides')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text(
          'Quick walkthrough',
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w700,
            color: forest,
          ),
        ),
        const SizedBox(height: 12),
        _steps(walkthrough),
        const SizedBox(height: 24),
        const Text(
          'User manual',
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w700,
            color: forest,
          ),
        ),
        const Text(
          'Tap a topic to see the steps.',
          style: TextStyle(color: muted),
        ),
        const SizedBox(height: 12),
        for (final topic in manual.entries)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: ExpansionTile(
              title: Text(topic.key),
              childrenPadding: const EdgeInsets.all(16),
              expandedCrossAxisAlignment: CrossAxisAlignment.stretch,
              children: [_steps(topic.value)],
            ),
          ),
      ],
    ),
  );

  static Widget _steps(List<String> steps) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (var i = 0; i < steps.length; i++)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Text(
            '${i + 1}. ${steps[i]}',
            style: const TextStyle(color: ink, height: 1.5),
          ),
        ),
    ],
  );
}
