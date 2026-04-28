import UIKit
import Capacitor
import CoreLocation

@UIApplicationMain
class AppDelegate: UIResponder, UIApplicationDelegate, CLLocationManagerDelegate {

    var window: UIWindow?
    private let locationManager = CLLocationManager()
    private let guidanceShownKey = "ios_tracking_always_guidance_shown_v1"

    func application(_ application: UIApplication, didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?) -> Bool {
        configureLocationManager()
        requestAlwaysLocationIfNeeded()
        applyForegroundLocationStrategy()
        application.setMinimumBackgroundFetchInterval(UIApplication.backgroundFetchIntervalMinimum)
        return true
    }

    func applicationWillResignActive(_ application: UIApplication) {
        // Sent when the application is about to move from active to inactive state. This can occur for certain types of temporary interruptions (such as an incoming phone call or SMS message) or when the user quits the application and it begins the transition to the background state.
        // Use this method to pause ongoing tasks, disable timers, and invalidate graphics rendering callbacks. Games should use this method to pause the game.
    }

    func applicationDidEnterBackground(_ application: UIApplication) {
        applyBackgroundLocationStrategy()
    }

    func applicationWillEnterForeground(_ application: UIApplication) {
        applyForegroundLocationStrategy()
    }

    func applicationDidBecomeActive(_ application: UIApplication) {
        requestAlwaysLocationIfNeeded()
        forceResumeLocationRefresh()
    }

    func applicationWillTerminate(_ application: UIApplication) {
        // Called when the application is about to terminate. Save data if appropriate. See also applicationDidEnterBackground:.
    }

    func application(_ app: UIApplication, open url: URL, options: [UIApplication.OpenURLOptionsKey: Any] = [:]) -> Bool {
        // Called when the app was launched with a url. Feel free to add additional processing here,
        // but if you want the App API to support tracking app url opens, make sure to keep this call
        return ApplicationDelegateProxy.shared.application(app, open: url, options: options)
    }

    func application(_ application: UIApplication, continue userActivity: NSUserActivity, restorationHandler: @escaping ([UIUserActivityRestoring]?) -> Void) -> Bool {
        // Called when the app was launched with an activity, including Universal Links.
        // Feel free to add additional processing here, but if you want the App API to support
        // tracking app url opens, make sure to keep this call
        return ApplicationDelegateProxy.shared.application(application, continue: userActivity, restorationHandler: restorationHandler)
    }

    func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        handleAuthorization(manager.authorizationStatus)
    }

    func locationManager(_ manager: CLLocationManager, didChangeAuthorization status: CLAuthorizationStatus) {
        handleAuthorization(status)
    }

    func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        // Keep native layer lightweight: ignore transient location failures.
    }

    func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        // Native layer only stabilizes iOS runtime behavior.
    }

    func locationManager(_ manager: CLLocationManager, didVisit visit: CLVisit) {
        // Low-power backup trigger while app is backgrounded/suspended.
        triggerVisitRecoveryRefresh()
    }

    func application(_ application: UIApplication, performFetchWithCompletionHandler completionHandler: @escaping (UIBackgroundFetchResult) -> Void) {
        forceResumeLocationRefresh()
        completionHandler(.newData)
    }

    private func configureLocationManager() {
        locationManager.delegate = self
        locationManager.allowsBackgroundLocationUpdates = true
        locationManager.pausesLocationUpdatesAutomatically = false
        locationManager.activityType = .otherNavigation
        locationManager.desiredAccuracy = kCLLocationAccuracyBest
        locationManager.distanceFilter = kCLDistanceFilterNone
        locationManager.startMonitoringVisits()
    }

    private func requestAlwaysLocationIfNeeded() {
        let status = locationManager.authorizationStatus
        if status == .notDetermined {
            locationManager.requestAlwaysAuthorization()
            return
        }
        if status == .authorizedWhenInUse {
            locationManager.requestAlwaysAuthorization()
            showAlwaysLocationGuidanceOnce()
        }
    }

    private func handleAuthorization(_ status: CLAuthorizationStatus) {
        if status == .authorizedAlways {
            if UIApplication.shared.applicationState == .background {
                applyBackgroundLocationStrategy()
            } else {
                applyForegroundLocationStrategy()
            }
            return
        }
        if status == .authorizedWhenInUse {
            locationManager.requestAlwaysAuthorization()
            showAlwaysLocationGuidanceOnce()
        }
    }

    private func applyForegroundLocationStrategy() {
        locationManager.desiredAccuracy = kCLLocationAccuracyBest
        locationManager.distanceFilter = kCLDistanceFilterNone
        locationManager.stopMonitoringSignificantLocationChanges()
        locationManager.startUpdatingLocation()
    }

    private func applyBackgroundLocationStrategy() {
        locationManager.stopUpdatingLocation()
        locationManager.startMonitoringSignificantLocationChanges()
        locationManager.startMonitoringVisits()
    }

    private func forceResumeLocationRefresh() {
        if locationManager.authorizationStatus == .authorizedAlways || locationManager.authorizationStatus == .authorizedWhenInUse {
            locationManager.requestLocation()
            locationManager.startUpdatingLocation()
        }
    }

    private func triggerVisitRecoveryRefresh() {
        guard locationManager.authorizationStatus == .authorizedAlways || locationManager.authorizationStatus == .authorizedWhenInUse else {
            return
        }
        locationManager.requestLocation()
        locationManager.startUpdatingLocation()
        DispatchQueue.main.asyncAfter(deadline: .now() + 8.0) { [weak self] in
            guard let self = self else { return }
            if UIApplication.shared.applicationState == .background {
                self.locationManager.stopUpdatingLocation()
                self.locationManager.startMonitoringSignificantLocationChanges()
            }
        }
    }

    private func showAlwaysLocationGuidanceOnce() {
        if UserDefaults.standard.bool(forKey: guidanceShownKey) {
            return
        }
        UserDefaults.standard.set(true, forKey: guidanceShownKey)
        DispatchQueue.main.async {
            guard let vc = self.window?.rootViewController else { return }
            let alert = UIAlertController(
                title: "Tracking permission",
                message: "Please select Always Allow Location in iOS settings to keep tracking reliable in the background.",
                preferredStyle: .alert
            )
            alert.addAction(UIAlertAction(title: "Open Settings", style: .default, handler: { _ in
                guard let url = URL(string: UIApplication.openSettingsURLString) else { return }
                UIApplication.shared.open(url)
            }))
            alert.addAction(UIAlertAction(title: "Later", style: .cancel))
            vc.present(alert, animated: true)
        }
    }
}
