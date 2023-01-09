const Encore = require('@symfony/webpack-encore');

const WebpackBar = require('webpackbar');
const MediaQueryPlugin = require('@glitchr/media-query-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin')

Encore.addPlugin(new WebpackBar())

    .setOutputPath('./src/Resources/public/')
    .setPublicPath('/bundles/base')
    .setManifestKeyPrefix('.')

    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .copyFiles({
        from: './node_modules/@fortawesome/fontawesome-free/metadata/',
        pattern: /icons.yml$/,
        to: 'metadata/[name].[ext]'
    })
    .copyFiles({
        from: './node_modules/@fortawesome/fontawesome-free/webfonts/',
        to: 'fonts/[name].[hash].[ext]'
    })

    .copyFiles({
        from: './node_modules/bootstrap-icons/font/fonts',
        to: 'fonts/[name].[ext]'
    })
    .copyFiles({
        from: './node_modules/@fortawesome/fontawesome-free/webfonts/',
        to: 'fonts/[name].[hash].[ext]'
    })

    .copyFiles({
        from: './node_modules/country-flag-icons/3x2/',
        to: 'images/flags/[path][name].[ext]',
        pattern: /\.svg$/
    })

    .copyFiles({
        from: './assets/styles/images/flags/',
        to: 'images/flags/[path][name].[ext]',
        pattern: /\.svg$/
    })
    .copyFiles({
        from: './assets/styles/fonts',
        to: 'fonts/[path][name].[ext]'
    })
    .copyFiles({
        from: './assets/styles/images/bundles/',
        to: 'images/bundles/[path][name].[ext]'
    })
    .copyFiles({
        from: './assets/styles/images/',
        to: 'images/[path][name].[ext]',
        pattern: /\.svg$/
    })

    .configureCssMinimizerPlugin((options) => {
        options.minimizerOptions = { preset: ['default', {svgo: false}] };
    })

    .disableSingleRuntimeChunk()
    .configureCssMinimizerPlugin()

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

    .addEntry('base-async', './assets/base-async.js')
    .addEntry('base-defer', './assets/base-defer.js')
    .addEntry('easyadmin-async', './assets/easyadmin-async.js')
    .addEntry('form-defer', './assets/form-defer.js')

    .enableSassLoader()
    .enablePostCssLoader()

    .addLoader({
        test: /\.scss$/,
        use: [
            MediaQueryPlugin.loader,
            'postcss-loader'
        ]
    })

    .addPlugin(new MediaQueryPlugin({
        include: ["base-async", "easyadmin-async", "form-defer"],
        queries: {

          // Standard
          'all and (min-width: 1281px)': 'desktop',
          'all and (min-width: 1025px) and (max-width: 1280px)': 'laptop',
          'all and (min-width: 471px) and (max-width: 1024px)': 'tablet',
          'all and (min-width: 471px) and (max-width: 1024px) and (orientation: landscape)': 'tablet-landscape',
          'all and (max-width: 470px)': 'mobile',
          'all and (max-width: 470px) and (orientation: landscape)': 'mobile-landscape'
        }
    }))
;

module.exports = Encore.getWebpackConfig();
