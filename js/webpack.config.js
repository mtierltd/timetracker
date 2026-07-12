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
    timer: './src/timer.js',
    clients: './src/clients.js',
    dashboard: './src/dashboard.js',
    projects: './src/projects.js',
    reports: './src/reports.js',
    tags: './src/tags.js',
    goals: './src/goals.js',
    timelines: './src/timelines.js',
    timelinesadmin: './src/timelines-admin.js',
    app: './src/app/app.js',
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/dist'
  },
  resolve: {
    alias: {
        'jquery-ui': 'jqueryui'
    }
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

