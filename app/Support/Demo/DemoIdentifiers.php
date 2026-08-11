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

    public const PRIMARY_PRODUCER_CODES = [
        'DEMO-TS-P001',
        'DEMO-TS-P002',
        'DEMO-TS-P003',
        'DEMO-TS-P004',
        'DEMO-TS-P005',
        'DEMO-TS-P006',
        'DEMO-TS-P007',
        'DEMO-TS-P008',
        'DEMO-TS-P009',
        'DEMO-TS-P010',
        'DEMO-TS-P011',
        'DEMO-TS-P012',
    ];

    public const NETWORK_PRODUCER_CODES = [
        'DEMO-ML-P001',
        'DEMO-ML-P002',
        'DEMO-ML-P003',
        'DEMO-ML-P004',
        'DEMO-ML-P005',
        'DEMO-ML-P006',
    ];

    public const FORECAST_CODE =
        'DEMO-FRC-KANGKUNG-400';

    public const FORECAST_TARGET_VOLUME =
        '400.000000';

    public const PRIMARY_BASELINE_PRODUCER_CODE =
        'DEMO-TS-P001';

    public const PRIMARY_BASELINE_VOLUME =
        '250.000000';

    public const PRIMARY_RISK_PRODUCER_CODE =
        'DEMO-TS-P002';

    public const PRIMARY_RISK_VOLUME =
        '150.000000';
    
    public const FALLBACK_REQUEST_VOLUME =
    '150.000000';

public const FALLBACK_REQUEST_NOTE =
    'CONTROLLED DEMO SIMULATION — FALLBACK REQUEST 150 KG.';

    public const NETWORK_SOURCE_PRODUCER_CODE =
    'DEMO-ML-P001';

public const NETWORK_SOURCE_VOLUME =
    '160.000000';

public const NETWORK_SOURCE_HARVEST_NOTE =
    'CONTROLLED DEMO SIMULATION — NETWORK EXPECTED HARVEST 160 KG.';

public const NETWORK_SOURCE_COMMITMENT_NOTE =
    'CONTROLLED DEMO SIMULATION — NETWORK FALLBACK SOURCE 160 KG.';

    public const FALLBACK_OFFER_VOLUME =
    '160.000000';

public const FALLBACK_ACCEPTED_VOLUME =
    '150.000000';

public const FALLBACK_OFFER_NOTE =
    'CONTROLLED DEMO SIMULATION — NETWORK FALLBACK OFFER 160 KG.';

public const LOGISTICS_REQUIREMENT_CODE =
    'DEMO-KANGKUNG-LOGISTICS-CONFIRMATION';

public const DOCUMENT_REQUIREMENT_CODE =
    'DEMO-KANGKUNG-DOCUMENT-CONFIRMATION';

public const LOGISTICS_ITEM_NOTE =
    'CONTROLLED DEMO SIMULATION — LOGISTICS READINESS CONFIRMED.';

public const DOCUMENT_ITEM_NOTE =
    'CONTROLLED DEMO SIMULATION — FORECAST DOCUMENT READINESS CONFIRMED.';

    /**
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

    /**
     * @return array<int, string>
     */
    public static function producerCodes(): array
    {
        return [
            ...self::PRIMARY_PRODUCER_CODES,
            ...self::NETWORK_PRODUCER_CODES,
        ];
    }

    private function __construct()
    {
    }
}