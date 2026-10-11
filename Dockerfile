# صورة تشغيل التطوير: PHP + Apache مع امتداد MySQL.
# ملاحظة: لا تُنسخ ملفات المشروع داخل الصورة (يبقى الكود مُثبَّتًا من المستودع)،
# لذلك تنعكس أي تعديلات مباشرة دون إعادة بناء.
FROM php:8.2-apache

# composer لتثبيت تبعيات PHP (mPDF) من composer.lock عند إقلاع الحاوية — التبعيات لا تُنسخ داخل الصورة
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# gd مطلوبة لمكتبة mPDF (ولبناؤها تلزمه libpng-dev)، وpdo_mysql لقاعدة البيانات.
# unzip لازم لـ composer لفكّ حزم التبعيات (لا يوجد امتداد zip ولا unzip في الصورة الأساسية).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql gd \
    && a2enmod rewrite headers \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/prince-net.conf \
    && a2enconf prince-net

WORKDIR /var/www/html

EXPOSE 80
