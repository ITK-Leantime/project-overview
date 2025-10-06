const webpack = require('webpack');
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    devtool: false,
    entry: {
        'project-overview': ['./assets/project-overview.js', './assets/project-overview.css']
    },
    output: {
        path: path.resolve(__dirname, 'dist'),
        filename: 'js/[name].js',
        clean: true // Cleans the dist folder before each build
    },
    optimization: {
        minimize: true,
        minimizer: [
            new TerserPlugin({
                parallel: true, // Use multi-process parallel running
                terserOptions: {
                    compress: {
                        drop_console: false
                    }
                }
            })
        ],
        splitChunks: {
            chunks: 'all',
            cacheGroups: {
                vendors: {
                    test: /[\\/]node_modules[\\/]/,
                    name: 'vendors',
                    chunks: 'all'
                }
            }
        }
    },
    cache: {
        type: 'filesystem' // Enable filesystem caching
    },
    plugins: [
        new webpack.ProvidePlugin({
            $: 'jquery',
            jQuery: 'jquery',
        }),
        new MiniCssExtractPlugin({
            filename: 'css/[name].css'
        }),
    ],
    module: {
        rules: [
            {
                test: /\.css$/i,
                use: [
                    {
                        loader: MiniCssExtractPlugin.loader,
                        options: {
                            esModule: true,
                        }
                    },
                    {
                        loader: "css-loader",
                        options: {
                            sourceMap: false
                        }
                    }
                ],
            },
        ],
    },
    mode: 'production',
    performance: {
        hints: false // Disable performance hints
    }
};
