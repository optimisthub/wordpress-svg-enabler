<img src="https://ps.w.org/svg-enabler/assets/banner-1544x500.png?rev=1791214569" alt="SVG Enabler" style="float: left; width:100%; margin-bottom:30px" />

# SVG Enabler

[![CI](https://github.com/optimisthub/wordpress-svg-enabler/actions/workflows/ci.yml/badge.svg)](https://github.com/optimisthub/wordpress-svg-enabler/actions/workflows/ci.yml)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/plugins/svg-enabler/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

| | |
|---|---|
| **Minimum WordPress Sürümü** | 6.0 |
| **Test Edilen WordPress Sürümü** | 7.1 |
| **PHP** | 7.4+ |
| **Stabil Versiyon** | 2.0.0 |
| **Lisans** | GPLv2 ya da daha sonrası |
| **Lisans URI** | https://www.gnu.org/licenses/gpl-2.0.html |

SVG Enabler, WordPress medya yöneticisinden SVG uzantılı dosyaları güvenli şekilde yükleyip kullanabilmeniz için geliştirilen SVG desteği eklentisidir.

Her yüklenen SVG dosyası, sitenize kaydedilmeden **önce** temizlenir. SVG bir XML formatı olduğu için JavaScript, olay yöneticisi öznitelikleri ve harici referanslar taşıyabilir — bu da temizlenmemiş bir SVG yüklemenin, sitenize rastgele HTML ve JavaScript yüklemeye eşdeğer olduğu anlamına gelir.

## Neler temizlenir?

- `<script>` etiketleri ve satır içi JavaScript
- `onload`, `onclick`, `onerror` gibi olay yöneticisi öznitelikleri
- Bağlantılardaki ve özniteliklerdeki `javascript:` adresleri
- `<foreignObject>` içerikleri
- `<image>`, `<use>`, `href` ve `xlink:href` içindeki uzak referanslar
- `<style>` bloklarındaki uzak `url()` ve `@import` kuralları
- XML entity ve DTD tanımları (XXE)
- CSS `expression()` ve `behavior:` bildirimleri

## Özellikler

- SVG ve SVGZ yükleme desteği
- Dosya uploads klasörüne taşınmadan **önce** temizleme
- Aktif olarak geliştirilen `enshrined/svg-sanitize` kütüphanesi (0.22.0+)
- Medya kütüphanesinde ve düzenleyicide doğru görsel boyutları
- Blok düzenleyici, klasik düzenleyici ve öne çıkarılan görsel desteği
- Filtrelenebilir izin listeleri ve yetki gereksinimleri
- Ayar ekranı gerektirmez, kutudan çıktığı gibi çalışır

## Kurulum

### WordPress üzerinden kurulum

1. Eklentiler sayfasından **Yeni Ekle**'ye tıklayın
2. `SVG Enabler` araması yapın
3. **Kur** ve ardından **Etkinleştir**'e tıklayın
4. Medya kütüphanesinden SVG yükleyebilirsiniz

### Elle kurulum

1. Zip dosyasını indirip açın, içinden çıkan `svg-enabler` klasörünü `/wp-content/plugins/` dizinine yükleyin
2. Eklentiler menüsünden `SVG Enabler` eklentisini etkinleştirin

### Composer ile kurulum

Bu paket Packagist'te yayınlanmadığı için önce GitHub deposunu VCS deposu
olarak tanıtmanız gerekir:

```bash
composer config repositories.optimisthub-svg-enabler vcs https://github.com/optimisthub/wordpress-svg-enabler
composer require optimisthub/wordpress-svg-enabler
```

### Bedrock ile kurulum

[Bedrock](https://roots.io/bedrock/) kullanıyorsanız eklenti, `type`
alanı `wordpress-plugin` olduğu için `composer/installers` tarafından
doğru dizine yerleştirilir. Projenizin `composer.json` dosyasına şunları
ekleyin:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/optimisthub/wordpress-svg-enabler"
        }
    ],
    "require": {
        "optimisthub/wordpress-svg-enabler": "^2.0"
    },
    "extra": {
        "installer-paths": {
            "web/app/plugins/{$name}/": ["type:wordpress-plugin"]
        }
    },
    "config": {
        "allow-plugins": {
            "composer/installers": true
        }
    }
}
```

Ardından:

```bash
composer update optimisthub/wordpress-svg-enabler
```

Eklenti `web/app/plugins/wordpress-svg-enabler/` dizinine, `svg-sanitize`
bağımlılığıyla birlikte kurulur. Etkinleştirmek için:

```bash
wp plugin activate svg-enabler
```

> **Not:** `installer-paths` tanımı olmadan eklenti `vendor/` altına
> kurulur ve WordPress onu görmez. `composer/installers` paketinin
> kurulu ve izinli olduğundan emin olun.

WordPress eklentilerini Composer ile yönetmenin alternatif bir yolu için
[wpackagist.org](https://wpackagist.org/) deposuna bakabilirsiniz.

## Sıkça Sorulan Sorular

### Eklentiyi sitemde etkinleştirdim, neden Admin Panel'de göremiyorum?

SVG Enabler bir "kur ve unut" eklentisidir. Ayar alanı yoktur; eklenti etkinleştirildiği anda çalışmaya başlar.

### SVG yüklemeye kimlerin yetkisi var?

Varsayılan olarak yalnızca yöneticiler (`manage_options` yetkisi olanlar) SVG yükleyebilir. SVG kod çalıştırabilen tek görsel formatı olduğu için diğer roller engellenir.

`optimisthub_svg_enabler_capability` filtresi ile bu değiştirilebilir:

```php
// Editörlerin de SVG yüklemesine izin ver
add_filter( 'optimisthub_svg_enabler_capability', function ( $capability ) {
    return 'edit_others_posts';
} );
```

### İzin verilen etiket ve öznitelikleri değiştirebilir miyim?

Evet. `optimisthub_svg_enabler_allowed_tags` ve `optimisthub_svg_enabler_allowed_attributes` filtrelerini kullanabilirsiniz:

```php
add_filter( 'optimisthub_svg_enabler_allowed_tags', function ( $tags ) {
    $tags[] = 'use';
    return $tags;
} );

add_filter( 'optimisthub_svg_enabler_allowed_attributes', function ( $attributes ) {
    $attributes[] = 'target';
    return $attributes;
} );
```

### SVG dosyam neden reddedildi?

Dosya geçerli XML değilse, `<svg>` kök etiketi içermiyorsa veya temizlenemiyorsa reddedilir. Reddedilen dosyalar hiçbir zaman kaydedilmez. Dosyayı Inkscape veya Illustrator gibi bir vektör düzenleyici ile yeniden kaydetmeyi deneyin.

### Bu eklenti SVG kullanımını tamamen güvenli hale getirir mi?

Bilinen tehlikeli yapıları temizler ve sanitizer kütüphanesini güncel tutar. Ancak hiçbir sanitizer tam garanti sayılamaz — bu yüzden yükleme yetkisi varsayılan olarak yöneticilerle sınırlıdır. Yalnızca güvendiğiniz kaynaklardan SVG yükleyin.

## Geliştirme

```bash
# Bağımlılıkları kur
composer install

# PHP söz dizimi kontrolü
composer lint

# WordPress kod standartları
composer phpcs

# Güvenlik testleri (12 XSS/XXE vektörü)
php .tests/security-test.php

# Yanlış-pozitif testleri
php .tests/false-positive-test.php
```

## Versiyon Geçmişi

### 2.0.0

**Güvenlik**

- Uzak referanslar artık kaldırılıyor: `href`, `xlink:href`, `src` gibi özniteliklerdeki uzak adresler temizleniyor. Önceden yüklenen bir SVG dış sunucuya istek atabiliyordu.
- `<style>` bloklarındaki `@import`, uzak `url()`, `expression()` ve `behavior:` kaldırılıyor.
- `enshrined/svg-sanitize` 0.15.4 → **0.22.0**. Eski sürüm CVE-2025-55166, CVE-2022-23638 ve CVE-2019-10772'den etkileniyordu.
- SVG yükleme artık `manage_options` yetkisi istiyor. Eski kontrol, yalnızca multisite'ta geçerli olan `unfiltered_upload` kullanıyordu.

**Düzeltmeler**

- `svgFileValidator()` var olmayan `$this->sanitize()` metodunu çağırıyordu → her SVG yüklemesinde ölümcül hata.
- Aynı callback `wp_check_filetype_and_ext` için 4 argümanla kayıtlıydı ama 1 parametre tanımlıyordu.
- Filtrenin kendi içinden `wp_check_filetype_and_ext()` çağrılması (sonsuz döngü riski) kaldırıldı.
- İzin verilen öznitelik/etiket filtreleri uygulanıyor ama dönüş değerleri atılıyordu.
- SVG görselleri 1x1 piksel görünüyordu; gerçek boyutlar artık `width`/`height` veya `viewBox`'tan okunuyor.
- `srcset` artık SVG ekleri için bozuk kayıt üretmiyor.

**Diğer**

- Namespaced PSR-4 yapı (`OptimistHub\SvgEnabler`).
- `composer.json` eklendi — daha önce `.gitignore` içinde olduğu için bağımlılıklar sapmıştı.
- `ext-dom` / `ext-libxml` yoksa SVG yükleme etkinleştirilmiyor ve yönetici uyarısı gösteriliyor.

### 1.0.3

- Yazar adı düzeltmesi.

### 1.0.2 - 1.0.1

- GitHub Action eklemeleri.

### 1.0.0

- Kararlı sürüm.

## Bağlantılar

- [WordPress Eklenti Dizini](https://wordpress.org/plugins/svg-enabler/)
- [SVN Commit Geçmişi](https://plugins.trac.wordpress.org/log/svg-enabler/)
- [Destek Forumu](https://wordpress.org/support/plugin/svg-enabler/)
- [Sorun Bildir](https://github.com/optimisthub/wordpress-svg-enabler/issues)

## Lisans

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)

---

Geliştirici: [Optimist Hub](https://optimisthub.com)
