const path = require('path');
const webpack = require('webpack');

module.exports = {
    entry: './assets/project-overview.js',
    output: {
        path: path.resolve(__dirname, './dist/js/'),
        filename: 'project-overview.js',
    },
    plugins: [
    new webpack.ProvidePlugin({
        $: 'jquery',
        jQuery: 'jquery',
    }),
  ],
module: {
    rules: [
    {
        test: /\.css$/i,
        use: ["style-loader", "css-loader"],
    },
    ],
    },
    mode: 'production',
};
