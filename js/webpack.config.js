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
            test: /\.css$/,
            use: ['style-loader', 'css-loader'],
          },
          {
            test: /\.(jpg|png|jpeg|svg)$/,
            loader: 'file-loader'
        },
        ]
      },
};

