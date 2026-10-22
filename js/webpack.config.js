const fs = require('fs');
const webpack = require('webpack');
const { VueLoaderPlugin } = require('vue-loader');

const appVersion = fs
  .readFileSync(__dirname + '/../appinfo/info.xml', 'utf8')
  .match(/<version>([^<]+)<\/version>/)[1];

module.exports =  {
  //mode: 'development',
  devtool: 'cheap-module-source-map',
  entry: {
    app: './src/app/app.js',
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/dist'
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
          {
            test: /\.(jpg|png|jpeg|svg)$/,
            loader: 'file-loader'
        },
        ]
      },
  plugins: [
    new VueLoaderPlugin(),
    new webpack.DefinePlugin({
      appName: JSON.stringify('timetracker'),
      appVersion: JSON.stringify(appVersion),
    }),
  ],
};

