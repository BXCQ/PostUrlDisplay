<?php

namespace TypechoPlugin\PostUrlDisplay;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Radio;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 文章管理URL显示插件
 *
 * @package PostUrlDisplay
 * @author 璇
 * @version 1.0.8
 * @link https://blog.ybyq.wang
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        // 在admin页面的footer中插入JavaScript代码
        \Typecho\Plugin::factory('admin/footer.php')->end = __CLASS__ . '::addUrlColumn';
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        /** 显示位置 */
        $position = new Radio('position', [
            'after_title' => _t('在标题列后面'),
            'before_date' => _t('在日期列前面'),
            'after_date' => _t('在日期列后面')
        ], 'before_date', _t('URL列显示位置'), _t('选择URL列在表格中的显示位置'));
        $form->addInput($position);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 在管理页面footer中插入JavaScript代码添加URL列
     *
     * @access public
     * @return void
     */
    public static function addUrlColumn()
    {
        // 只在manage-posts.php页面生效
        if (basename($_SERVER['SCRIPT_NAME']) !== 'manage-posts.php') {
            return;
        }

        $config = Options::alloc()->plugin('PostUrlDisplay');
        $position = isset($config->position) ? $config->position : 'before_date';
        ?>
        <script type="text/javascript">
        (function() {
            // 等待页面加载完成
            document.addEventListener('DOMContentLoaded', function() {
                addUrlColumn();
            });
            
            // 如果页面已经加载完成，直接执行
            if (document.readyState === 'complete') {
                addUrlColumn();
            }
            
            function addUrlColumn() {
                var table = document.querySelector('.typecho-list-table');
                if (!table) return;
                
                var position = '<?php echo $position; ?>';
                var colgroup = table.querySelector('colgroup');
                var thead = table.querySelector('thead tr');
                var tbody = table.querySelector('tbody');
                
                if (!thead || !tbody) return;
                
                // 首先重新配置现有列的宽度以腾出空间
                adjustExistingColumns(colgroup, position);
                
                // 确定插入位置
                var insertIndex;
                var insertAfter = false;
                
                switch(position) {
                    case 'after_title':
                        insertIndex = 2; // 标题列的索引
                        insertAfter = true;
                        break;
                    case 'after_date':
                        insertIndex = thead.children.length - 1; // 最后一列（日期列）
                        insertAfter = true;
                        break;
                    case 'before_date':
                    default:
                        insertIndex = thead.children.length - 1; // 日期列前面
                        insertAfter = false;
                        break;
                }
                
                if (insertAfter) {
                    insertIndex++;
                }
                
                // 在colgroup中添加新列
                if (colgroup) {
                    var newCol = document.createElement('col');
                    newCol.width = getUrlColumnWidth(position);
                    newCol.className = 'kit-hidden-mb';
                    
                    if (insertIndex >= colgroup.children.length) {
                        colgroup.appendChild(newCol);
                    } else {
                        colgroup.insertBefore(newCol, colgroup.children[insertIndex]);
                    }
                }
                
                // 在表头添加URL列
                var urlHeader = document.createElement('th');
                urlHeader.className = 'kit-hidden-mb';
                urlHeader.textContent = 'URL地址';
                
                if (insertIndex >= thead.children.length) {
                    thead.appendChild(urlHeader);
                } else {
                    thead.insertBefore(urlHeader, thead.children[insertIndex]);
                }
                
                // 在每行数据中添加URL单元格
                var rows = tbody.querySelectorAll('tr');
                rows.forEach(function(row) {
                    // 跳过"没有任何文章"的行
                    if (row.querySelector('td[colspan]')) {
                        // 更新colspan
                        var colspan = row.querySelector('td[colspan]');
                        if (colspan) {
                            var currentSpan = parseInt(colspan.getAttribute('colspan')) || 6;
                            colspan.setAttribute('colspan', currentSpan + 1);
                        }
                        return;
                    }
                    
                    var urlCell = document.createElement('td');
                    urlCell.className = 'kit-hidden-mb';
                    
                    // 从标题列中提取链接
                    var titleCell = row.children[2]; // 标题列
                    if (titleCell) {
                        var viewLink = titleCell.querySelector('a[title*="浏览"]');
                        if (viewLink) {
                            var url = viewLink.href;
                            var urlText = document.createElement('a');
                            urlText.href = url;
                            urlText.target = '_blank';
                            urlText.textContent = url;
                            urlText.className = 'url-link';
                            urlText.title = '点击复制URL并在新窗口打开';
                            
                            // 添加复制功能
                            urlText.onclick = function(e) {
                                e.preventDefault();
                                copyToClipboard(url);
                                showNotification('URL已复制到剪贴板');
                                setTimeout(function() {
                                    window.open(url, '_blank');
                                }, 500);
                            };
                            
                            urlCell.appendChild(urlText);
                        } else {
                            // 草稿文章没有浏览链接
                            urlCell.innerHTML = '<span class="draft-url">草稿无URL</span>';
                        }
                    }
                    
                    if (insertIndex >= row.children.length) {
                        row.appendChild(urlCell);
                    } else {
                        row.insertBefore(urlCell, row.children[insertIndex]);
                    }
                });
            }
            
            // 调整现有列的宽度以腾出空间给URL列
            function adjustExistingColumns(colgroup, position) {
                if (!colgroup) return;
                
                var cols = colgroup.children;
                
                // 固定列宽度 (复选框20px, 评论4%, 作者9%)
                if (cols[0]) cols[0].width = '20px'; 
                if (cols[1]) cols[1].width = '4%';   
                if (cols[3]) cols[3].width = '9%';  
                
                // 根据位置调整其他列宽，URL宽度缩短，空间给标题/分类/日期
                switch(position) {
                    case 'after_title':
                        if (cols[2]) cols[2].width = '35%'; // 标题列 (原33% + 2%)
                        if (cols[4]) cols[4].width = '14%'; // 分类列 (原13% + 1%)
                        if (cols[5]) cols[5].width = '12%'; // 日期列 (原11% + 1%)
                        break;
                    case 'after_date':
                        if (cols[2]) cols[2].width = '32%'; // 标题列 (原30% + 2%)
                        if (cols[4]) cols[4].width = '14%'; // 分类列 (原13% + 1%)
                        if (cols[5]) cols[5].width = '9%';  // 日期列 (原8% + 1%)
                        break;
                    case 'before_date':
                    default:
                        if (cols[2]) cols[2].width = '33%'; // 标题列 (原31% + 2%)
                        if (cols[4]) cols[4].width = '13%'; // 分类列 (原12% + 1%)
                        if (cols[5]) cols[5].width = '11%'; // 日期列 (原10% + 1%)
                        break;
                }
            }
            
            // 根据位置获取URL列的宽度
            function getUrlColumnWidth(position) {
                // URL宽度缩短 (原30-36%)
                switch(position) {
                    case 'after_title':
                        return '26%'; // 原30% - 4%
                    case 'after_date':
                        return '32%'; // 原36% - 4%
                    case 'before_date':
                    default:
                        return '30%'; // 原34% - 4%
                }
            }
            
            // 复制到剪贴板函数
            function copyToClipboard(text) {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text);
                } else {
                    // 兼容性方案
                    var textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                }
            }
            
            // 显示通知
            function showNotification(message) {
                var notification = document.createElement('div');
                notification.textContent = message;
                notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
                document.body.appendChild(notification);
                
                setTimeout(function() {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 2000);
            }
        })();
        </script>
        
        <style type="text/css">
        /* 设置容器最大宽度 */
        @media (min-width: 1200px) {
            .container {
                max-width: 1560px;
            }
        }
        
        /* 调整表格样式以更好地显示URL */
        .typecho-list-table {
            table-layout: fixed; /* 固定表格布局，确保列宽生效 */
            width: 100%;
            min-width: 1400px; /* 增大最小宽度 */
        }
        
        .typecho-list-table td.kit-hidden-mb,
        .typecho-list-table th.kit-hidden-mb {
            word-wrap: break-word;
            overflow: hidden;
        }
        
        /* 修复复选框和序号重叠问题 */
        .typecho-list-table colgroup col:first-child {
            width: 20px !important; /* 复选框列宽度减少到20px */
        }
        
        .typecho-list-table colgroup col:nth-child(2) {
            width: 4% !important; /* 评论数列宽度减少到4% */
        }
        
        /* 作者列的宽度将在JS中通过colgroup子元素索引动态设置，无需在此固定 */
        /* 但我们可以调整其单元格的内边距等基础样式 */
        .typecho-list-table th:nth-child(4), /* 假设作者列是第4个th/td (1-based index for :nth-child) */
        .typecho-list-table td:nth-child(4) {
            /* 如果需要，可以在这里添加针对作者列的特定样式，如内边距 */
            padding-left: 6px;
            padding-right: 6px;
        }
        
        .typecho-list-table td:first-child,
        .typecho-list-table th:first-child {
            text-align: center;
            padding: 8px 4px;
            min-width: 30px;
        }
        
        .typecho-list-table td:nth-child(2),
        .typecho-list-table th:nth-child(2) {
            text-align: center;
            padding: 8px 6px;
        }
        
        .typecho-list-table .url-link {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px; /* 增大字体大小到14px */
            color: #666;
            text-decoration: none;
            padding: 3px 6px;
            border-radius: 4px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        
        .typecho-list-table .url-link:hover {
            background-color: #f0f8ff;
            color: #1e90ff;
            white-space: normal;
            word-break: break-all;
            max-height: 80px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
            z-index: 10;
            position: relative;
            padding: 6px 8px;
        }
        
        .typecho-list-table .draft-url {
            color: #ccc;
            font-size: 14px; /* 同步增大字体大小 */
            font-style: italic;
            padding: 3px 6px;
        }
        
        /* 日期后显示的特殊优化样式 - 字体大小将继承 .url-link 的设置 */
        .typecho-list-table tr td:last-child .url-link {
            /* font-size: 13px; */ /* 移除这里的重复设置，将继承 */
            padding: 4px 8px;
            border: 1px solid #e8e8e8;
        }
        
        /* 响应式设计 */
        @media (max-width: 1400px) {
            .typecho-list-table {
                min-width: 1200px;
            }
        }
        
        /* 响应式设计调整字体 */
        @media (max-width: 1200px) {
            .typecho-list-table {
                min-width: 1100px;
            }
            .typecho-list-table .url-link,
            .typecho-list-table .draft-url {
                font-size: 12px; /* 中等屏幕恢复到12px */
            }
        }
        
        @media (max-width: 768px) {
            .typecho-list-table .kit-hidden-mb {
                display: none !important;
            }
        }
        
        /* 确保表格容器可以水平滚动 */
        .typecho-table-wrap {
            overflow-x: auto;
            min-width: 100%;
            padding-bottom: 10px; /* 为滚动条留出空间 */
        }
        
        /* 在大屏幕上确保表格有足够宽度 */
        @media (min-width: 1400px) {
            .typecho-list-table {
                min-width: 1500px; /* 增大到1500px */
            }
        }
        
        @media (min-width: 1600px) {
            .typecho-list-table {
                min-width: 1600px; /* 超大屏幕更大宽度 */
            }
        }
        </style>
        <?php
    }
} 