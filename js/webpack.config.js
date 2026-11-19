const webpack = require('webpack');
const { VueLoaderPlugin } = require('vue-loader');
// Same as <version> in appinfo/info.xml (enforced by the version check in CI)
const { version: appVersion } = require('./package.json');

// npm run build: production (minified, no source maps), used for releases.
// npm run dev / npm run watch: development, with source maps.
module.exports = (env, argv) => {
  const production = argv.mode !== 'development';

  return {
    mode: production ? 'production' : 'development',
    // Source maps only for development: in production they'd double the size
    // of js/dist and end up in the release archive.
    devtool: production ? false : 'eval-cheap-module-source-map',
    entry: {
      app: './src/app/app.js',
    },
    output: {
      filename: '[name].js',
      path: __dirname + '/dist',
    },
    module: {
      rules: [
        {
          test: /\.vue$/,
          loader: 'vue-loader',
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader'],
        },
      ],
    },
    plugins: [
      new VueLoaderPlugin(),
      // @nextcloud/vue expects these to be replaced at build time
      // (it logs an error and falls back to defaults otherwise)
      new webpack.DefinePlugin({
        appName: JSON.stringify('timetracker'),
        appVersion: JSON.stringify(appVersion),
      }),
    ],
    performance: {
      // @nextcloud/vue makes the bundle much bigger than webpack's 244 KiB
      // default. Warn only if it grows clearly beyond today's ~1.8 MiB.
      maxAssetSize: 2.5 * 1024 * 1024,
      maxEntrypointSize: 2.5 * 1024 * 1024,
    },
    optimization: {
      // Nextcloud loads the app with a single script tag (templates/spa.php),
      // so the runtime stays in app.js instead of a separate chunk.
      runtimeChunk: false,
    },
  };
};
