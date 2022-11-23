var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('./src/Resources/public/')
    .setPublicPath('/bundles/base')
    .setManifestKeyPrefix('.')

    .cleanupOutputBeforeBuild()
    .enableSassLoader()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

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
        options.minimizerOptions = {
            preset: [
                'default',
                {
                    // disabled to fix these issues: https://github.com/EasyCorp/EasyAdminBundle/pull/5171
                    svgo: false,
                },
            ]
        };
    })

    .disableSingleRuntimeChunk()

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

    .addEntry('base', './assets/base.js')
    .addEntry('easyadmin', './assets/easyadmin.js')
    .addEntry('form', './assets/form.js');

module.exports = Encore.getWebpackConfig();
