import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/errors.dart';
import '../../widgets/section_card.dart';
import '../authentication/app_state.dart';

class LocationScreen extends StatefulWidget {
  const LocationScreen({super.key});

  @override
  State<LocationScreen> createState() => _LocationScreenState();
}

class _LocationScreenState extends State<LocationScreen> {
  String? _error;

  @override
  Widget build(BuildContext context) {
    final appState = context.watch<AppState>();
    final tracking = appState.locationLiveTracking;
    final last = appState.lastLiveLocation;

    return Scaffold(
      appBar: AppBar(title: const Text('Location sharing')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const SectionCard(
            title: 'Live tracking',
            child: Text(
              'When enabled, this device sends GPS to the admin about once per second.',
            ),
          ),
          SwitchListTile(
            title: const Text('Send location every second'),
            subtitle: Text(tracking ? 'Live tracking ON' : 'Live tracking OFF'),
            value: tracking,
            onChanged: (value) async {
              setState(() => _error = null);
              try {
                if (value) {
                  await appState.startLocationLiveTracking();
                } else {
                  await appState.stopLocationLiveTracking();
                }
              } catch (e) {
                setState(() => _error = e.toString());
              }
            },
          ),
          if (last != null)
            SectionCard(
              title: 'Last reported',
              child: Text(
                '${last.latitude.toStringAsFixed(5)}, '
                '${last.longitude.toStringAsFixed(5)}\n'
                'accuracy ${last.accuracy?.toStringAsFixed(0) ?? '—'} m',
              ),
            ),
          if (_error != null)
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          const SizedBox(height: 8),
          FilledButton(
            onPressed: () async {
              setState(() => _error = null);
              try {
                final loc = await appState.locationService
                    .getCurrentLocation(requestIfNeeded: true);
                await appState.locationService.uploadLocation(loc);
                appState.setLastLiveLocation(loc);
              } on PermissionDeniedException catch (e) {
                setState(() => _error = e.toString());
              } catch (e) {
                setState(() => _error = e.toString());
              }
            },
            child: const Text('Share once now'),
          ),
        ],
      ),
    );
  }
}
