<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Support;

/** POS document code prefixes (isolated from ERP DocumentCodeService). */
final class PosDocumentCodes
{
    public const TERMINAL = 'POS-';
    public const SHIFT = 'SH-';
    public const ORDER = 'PSO-';
    public const RETURN = 'PRN-';
    public const QUOTE = 'PQT-';
    public const SUSPEND = 'PSU-';
    public const X_REPORT = 'PX-';
    public const Z_REPORT = 'PZ-';
}
