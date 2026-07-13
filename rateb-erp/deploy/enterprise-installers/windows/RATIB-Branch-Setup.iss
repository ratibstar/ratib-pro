; RATIB ERP — Phase D.2 Enterprise Branch Appliance
; Inno Setup 6 — produces RATIB-Branch-Setup.exe
; Silent: RATIB-Branch-Setup.exe /SILENT
; Repair: re-run setup (preserves storage\branch SQLite)

#define MyAppName "RATIB Branch"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "RATIB"
#define MyAppURL "https://rateb.sa"
#define MyAppExeName "Open RATIB Branch.url"
#define InstallDirName "RATIB Branch"

#ifndef PayloadDir
  #define PayloadDir "..\..\..\storage\branch\enterprise-installers\payload\windows"
#endif

[Setup]
AppId={{A7C3E9B1-4D2F-4F8A-9C11-8E2B6D0F4A31}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
DefaultDirName={autopf}\{#InstallDirName}
DefaultGroupName=RATIB Branch
DisableProgramGroupPage=no
LicenseFile=
OutputDir=..\..\..\storage\branch\enterprise-installers
OutputBaseFilename=RATIB-Branch-Setup
Compression=lzma2
SolidCompression=yes
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0
WizardStyle=modern
UninstallDisplayName=RATIB Branch
CloseApplications=no
RestartIfNeededByRun=no

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a &desktop shortcut"; GroupDescription: "Additional icons:"
Name: "firewall"; Description: "Add Windows Firewall rule for Branch Web"; GroupDescription: "Network:"; Flags: checkedonce

[Files]
; Full ERP payload staged by build.ps1
Source: "{#PayloadDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "storage\branch\rateb-branch.sqlite,storage\branch\serve.env,storage\sessions\*"
; Enterprise installer helpers
Source: "*"; DestDir: "{app}\deploy\enterprise-installers\windows"; Flags: ignoreversion; Excludes: "build.ps1,RATIB-Branch-Setup.iss"
Source: "..\common\*"; DestDir: "{app}\deploy\enterprise-installers\common"; Flags: ignoreversion
Source: "..\universal\*"; DestDir: "{app}\deploy\enterprise-installers\universal"; Flags: ignoreversion
Source: "..\zero-touch\*"; DestDir: "{app}\deploy\enterprise-installers\zero-touch"; Flags: ignoreversion recursesubdirs
Source: "..\systemd\*"; DestDir: "{app}\deploy\enterprise-installers\systemd"; Flags: ignoreversion

[Dirs]
Name: "{app}\storage\branch"
Name: "{app}\storage\branch\logs"
Name: "{app}\storage\branch\backups"
Name: "{app}\storage\branch\tmp"
Name: "{app}\storage\sessions"
Name: "{app}\runtime\php"

[Icons]
Name: "{group}\RATIB ERP"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\deploy\enterprise-installers\zero-touch\windows\RatibLauncher.ps1"" -InstallRoot ""{app}"""
Name: "{group}\Uninstall RATIB Branch"; Filename: "{uninstallexe}"
Name: "{autodesktop}\RATIB ERP"; Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\deploy\enterprise-installers\zero-touch\windows\RatibLauncher.ps1"" -InstallRoot ""{app}"""; Tasks: desktopicon

[Run]
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\deploy\enterprise-installers\universal\install-universal.ps1"" -InstallRoot ""{app}"" -Silent"; \
  StatusMsg: "Universal Branch Appliance (runtime, port, services, health)..."; \
  Flags: runhidden waituntilterminated
Filename: "{app}\storage\branch\post-install.html"; Description: "Open install summary"; Flags: postinstall shellexec skipifsilent
Filename: "http://127.0.0.1/"; Description: "Open RATIB Branch"; Flags: postinstall shellexec skipifsilent

[UninstallRun]
Filename: "powershell.exe"; \
  Parameters: "-NoProfile -ExecutionPolicy Bypass -File ""{app}\deploy\enterprise-installers\windows\uninstall-branch.ps1"" -InstallRoot ""{app}"" -KeepDatabase ask"; \
  RunOnceId: "UninstallBranchAsk"; Flags: runhidden waituntilterminated

[Code]
function InitializeSetup(): Boolean;
begin
  Result := True;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  { Upgrade: never delete storage\branch SQLite }
  Result := '';
end;
