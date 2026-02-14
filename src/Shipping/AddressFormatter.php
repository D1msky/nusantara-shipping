<?php

declare(strict_types=1);

namespace Nusantara\Shipping;

use Nusantara\Data\RepositoryInterface;

final class AddressFormatter
{
    /** @var array<string, array{format: string, case: string, separator: string}> */
    private array $styles;

    public function __construct(?array $customStyles = null)
    {
        $this->styles = array_merge(
            $this->defaultStyles(),
            (array) ($customStyles ?? config('nusantara.shipping_styles', []))
        );
    }

    public function format(
        RepositoryInterface $repository,
        string $regionCode,
        ?string $format = null,
        ?string $style = null
    ): ?string {
        $hierarchy = $this->hierarchy($repository, $regionCode);
        if ($hierarchy === null) {
            return null;
        }

        $config = $style && isset($this->styles[$style])
            ? $this->styles[$style]
            : $this->styles['default'];

        $template = $format ?? $config['format'];
        $case = $config['case'] ?? 'title';
        $separator = $config['separator'] ?? ', ';

        $replace = [
            ':village' => $this->applyCase($hierarchy['village']['name'] ?? '', $case),
            ':district' => $this->applyCase($hierarchy['district']['name'] ?? '', $case),
            ':regency' => $this->applyCase($hierarchy['regency']['name'] ?? '', $case),
            ':province' => $this->applyCase($hierarchy['province']['name'] ?? '', $case),
            ':postal' => $hierarchy['village']['postal_code'] ?? '',
        ];

        $line = str_replace(array_keys($replace), array_values($replace), $template);
        $line = preg_replace('/\s+/', ' ', trim($line));
        return str_replace($separator . $separator, $separator, $line);
    }

    /** @return array{province: array, regency: array, district: array, village: array}|null */
    private function hierarchy(RepositoryInterface $repository, string $code): ?array
    {
        $code = trim(str_replace(' ', '', $code));
        $parts = explode('.', $code);
        $province = null;
        $regency = null;
        $district = null;
        $village = null;

        if (count($parts) >= 1 && $parts[0] !== '') {
            $province = $repository->findProvince($parts[0]);
        }
        if (count($parts) >= 2 && $province) {
            $regency = $repository->findRegency($parts[0] . '.' . $parts[1]);
        }
        if (count($parts) >= 3 && $regency) {
            $district = $repository->findDistrict($parts[0] . '.' . $parts[1] . '.' . $parts[2]);
        }
        if (count($parts) >= 4 && $district) {
            $village = $repository->findVillage($code);
        }

        if (! $province) {
            return null;
        }

        return [
            'province' => $province,
            'regency' => $regency ?? [],
            'district' => $district ?? [],
            'village' => $village ?? [],
        ];
    }

    private function applyCase(string $value, string $case): string
    {
        return match (strtolower($case)) {
            'upper' => mb_strtoupper($value),
            'lower' => mb_strtolower($value),
            'title' => \Nusantara\Support\RegionCollection::toTitleCase($value),
            default => $value,
        };
    }

    /** @return array<string, array{format: string, case: string, separator: string}> */
    private function defaultStyles(): array
    {
        return [
            'default' => [
                'format' => ':village, :district, :regency, :province, :postal',
                'case' => 'title',
                'separator' => ', ',
            ],
            'jne' => [
                'format' => ':village, :district, :regency, :postal',
                'case' => 'upper',
                'separator' => ', ',
            ],
            'jnt' => [
                'format' => ':village :district :regency :province :postal',
                'case' => 'upper',
                'separator' => ' ',
            ],
            'sicepat' => [
                'format' => ':village, :district, :regency, :province, :postal',
                'case' => 'upper',
                'separator' => ', ',
            ],
            'pos_indonesia' => [
                'format' => ':village, :district, :regency, :province :postal',
                'case' => 'title',
                'separator' => ', ',
            ],
            'lion_parcel' => [
                'format' => ':village, :district, :regency, :province, :postal',
                'case' => 'upper',
                'separator' => ', ',
            ],
        ];
    }
}
