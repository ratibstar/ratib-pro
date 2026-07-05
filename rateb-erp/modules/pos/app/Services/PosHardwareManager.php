<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Contracts\PosCashDrawerHardwareInterface;
use Rateb\App\Pos\Contracts\PosCustomerDisplayInterface;
use Rateb\App\Pos\Contracts\PosNfcInterface;
use Rateb\App\Pos\Contracts\PosPrinterInterface;
use Rateb\App\Pos\Contracts\PosScaleInterface;
use Rateb\App\Pos\Contracts\PosScannerInterface;
use Rateb\App\Pos\Services\Drivers\NullPosCashDrawerHardware;
use Rateb\App\Pos\Services\Drivers\NullPosCustomerDisplay;
use Rateb\App\Pos\Services\Drivers\NullPosNfcReader;
use Rateb\App\Pos\Services\Drivers\NullPosPrinter;
use Rateb\App\Pos\Services\Drivers\NullPosScale;
use Rateb\App\Pos\Services\Drivers\NullPosScanner;

/** Hardware manager — printer, scanner, drawer, display, scale, NFC. */
final class PosHardwareManager
{
    private PosPrinterInterface $printer;
    private PosScannerInterface $scanner;
    private PosCashDrawerHardwareInterface $drawer;
    private PosCustomerDisplayInterface $display;
    private PosScaleInterface $scale;
    private PosNfcInterface $nfc;

    public function __construct()
    {
        $this->printer = new NullPosPrinter();
        $this->scanner = new NullPosScanner();
        $this->drawer = new NullPosCashDrawerHardware();
        $this->display = new NullPosCustomerDisplay();
        $this->scale = new NullPosScale();
        $this->nfc = new NullPosNfcReader();
    }

    public function printer(): PosPrinterInterface
    {
        return $this->printer;
    }

    public function scanner(): PosScannerInterface
    {
        return $this->scanner;
    }

    public function cashDrawer(): PosCashDrawerHardwareInterface
    {
        return $this->drawer;
    }

    public function customerDisplay(): PosCustomerDisplayInterface
    {
        return $this->display;
    }

    public function scale(): PosScaleInterface
    {
        return $this->scale;
    }

    public function nfc(): PosNfcInterface
    {
        return $this->nfc;
    }

    /** @return array<string, string> */
    public function deviceStatus(): array
    {
        return [
            'printer' => $this->printer->deviceId(),
            'scanner' => $this->scanner->deviceId(),
            'drawer' => $this->drawer->deviceId(),
            'display' => $this->display->deviceId(),
            'scale' => $this->scale->deviceId(),
            'nfc' => $this->nfc->deviceId(),
        ];
    }
}
