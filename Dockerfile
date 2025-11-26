FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y git  curl  libpng-dev  libonig-dev  libxml2-dev  zip  unzip  sqlite3  libsqlite3-dev  ffmpeg  build-essential  python3  python3-pip  python3-venv  libcairo2-dev  libpango1.0-dev  texlive-full
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_sqlite mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create a virtual environment for Python to avoid breaking system packages
ENV VIRTUAL_ENV=/opt/venv
RUN python3 -m venv $VIRTUAL_ENV
ENV PATH="$VIRTUAL_ENV/bin:$PATH"

# Install Python dependencies
# We install manim and google-generativeai as requested
RUN pip install --no-cache-dir manim google-generativeai

# Copy existing application directory contents
COPY . /var/www

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Expose port 8000
EXPOSE 8000

# Default command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
