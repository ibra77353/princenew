# صورة تشغيل التطوير: PHP + Apache مع امتداد MySQL.
# ملاحظة: لا تُنسخ ملفات المشروع داخل الصورة (يبقى الكود مُثبَّتًا من المستودع)،
# لذلك تنعكس أي تعديلات مباشرة دون إعادة بناء.
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
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
