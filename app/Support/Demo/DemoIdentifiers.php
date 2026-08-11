<?php

namespace App\Support\Demo;

final class DemoIdentifiers
{
    public const SPPG_CODE =
        'DEMO-SPPG-BADUNG';

    public const PRIMARY_KDKMP_CODE =
        'DEMO-KDKMP-TANI-SEJAHTERA';

    public const NETWORK_KDKMP_CODE =
        'DEMO-KDKMP-MITRA-LESTARI';

    public const SPPG_NAME =
        'SPPG Badung Demo';

    public const PRIMARY_KDKMP_NAME =
        'KDKMP Tani Sejahtera';

    public const NETWORK_KDKMP_NAME =
        'KDKMP Mitra Lestari';

    public const ADMIN_EMAIL =
        'demo.admin@siagapasok.local';

    public const SPPG_EMAIL =
        'sppg.badung@siagapasok.local';

    public const PRIMARY_OPERATOR_EMAIL =
        'tani.operator@siagapasok.local';

    public const PRIMARY_MANAGER_EMAIL =
        'tani.manager@siagapasok.local';

    public const NETWORK_OPERATOR_EMAIL =
        'mitra.operator@siagapasok.local';

    public const NETWORK_MANAGER_EMAIL =
        'mitra.manager@siagapasok.local';

    public const DEMO_PASSWORD =
        'SiagaPasokDemo2026!';

    /**
     * Akun yang akan dipakai oleh presentation
     * role-switch utility.
     *
     * System Admin sengaja tidak dimasukkan karena
     * final operational demo berfokus pada SPPG dan
     * dua KDKMP.
     *
     * @return array<int, string>
     */
    public static function operationalAccountEmails(): array
    {
        return [
            self::SPPG_EMAIL,
            self::PRIMARY_OPERATOR_EMAIL,
            self::PRIMARY_MANAGER_EMAIL,
            self::NETWORK_OPERATOR_EMAIL,
            self::NETWORK_MANAGER_EMAIL,
        ];
    }

    private function __construct()
    {
    }
}